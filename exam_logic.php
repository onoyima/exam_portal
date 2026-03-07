<?php
/**
 * Finalizes an objective exam attempt: calculates score and sets finish time.
 */
function finalize_objective_attempt($db, $exam_id, $student_id, $attempt_no)
{
    // Check if it's already finished
    $stmt = $db->prepare("SELECT finished_at, started_at FROM objective_attempts WHERE exam_id=? AND student_id=? AND attempt_no=?");
    $stmt->execute([$exam_id, $student_id, $attempt_no]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt || $attempt['finished_at'])
        return false;

    // Get exam duration for time_taken fallback
    $stmt_e = $db->prepare("SELECT duration_minutes FROM exams WHERE id=?");
    $stmt_e->execute([$exam_id]);
    $exam = $stmt_e->fetch(PDO::FETCH_ASSOC);
    $duration_sec = ($exam['duration_minutes'] ?? 0) * 60;

    // Calculate score
    $qs_stmt = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
    $qs_stmt->execute([$exam_id]);
    $questions = $qs_stmt->fetchAll(PDO::FETCH_ASSOC);

    $ans_stmt = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
    $ans_stmt->execute([$exam_id, $student_id, $attempt_no]);
    $answers = $ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $calculated_score = 0;
    foreach ($questions as $q) {
        if (isset($answers[$q['id']])) {
            $sel = $answers[$q['id']];
            if (strtoupper($sel) === strtoupper($q['correct_option'])) {
                $calculated_score += (isset($q['mark']) ? floatval($q['mark']) : 1);
            }
        }
    }

    // Update attempt as finished
    $now = date('Y-m-d H:i:s');
    $time_taken = strtotime($now) - strtotime($attempt['started_at']);
    // If we are finalising purely because of timeout, cap the time_taken
    if ($time_taken > $duration_sec && $duration_sec > 0)
        $time_taken = $duration_sec;

    $finish_stmt = $db->prepare("UPDATE objective_attempts SET finished_at=NOW(), time_taken=?, score=? WHERE exam_id=? AND student_id=? AND attempt_no=? AND finished_at IS NULL");
    return $finish_stmt->execute([$time_taken, $calculated_score, $exam_id, $student_id, $attempt_no]);
}

/**
 * Automatically find and finalize all expired attempts for an exam or student.
 */
function auto_finalize_expired_attempts($db, $exam_id = null, $student_id = null)
{
    $sql = "SELECT oa.exam_id, oa.student_id, oa.attempt_no 
            FROM objective_attempts oa 
            JOIN exams e ON oa.exam_id = e.id 
            WHERE oa.finished_at IS NULL 
            AND TIMESTAMPDIFF(SECOND, oa.started_at, NOW()) > (e.duration_minutes * 60) + 30"; // 30s grace period

    $params = [];
    if ($exam_id) {
        $sql .= " AND oa.exam_id = ?";
        $params[] = $exam_id;
    }
    if ($student_id) {
        $sql .= " AND oa.student_id = ?";
        $params[] = $student_id;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expired as $att) {
        finalize_objective_attempt($db, $att['exam_id'], $att['student_id'], $att['attempt_no']);
    }
    return count($expired);
}
?>
