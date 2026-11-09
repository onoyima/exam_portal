<?php
/**
 * Objective attempt lifecycle helpers used across the portal.
 * Marking itself lives in includes/question_engine.php.
 */
require_once __DIR__ . '/includes/question_engine.php';

/**
 * Finalizes an objective attempt: marks it and records the finish time.
 * Kept with its original signature for existing callers.
 */
function finalize_objective_attempt($db, $exam_id, $student_id, $attempt_no)
{
    $st = $db->prepare("SELECT id FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $st->execute([$exam_id, $student_id, $attempt_no]);
    $id = $st->fetchColumn();
    return $id ? qe_finalize_attempt($db, (int) $id) : false;
}

/**
 * Closes attempts whose time is used up or whose exam window has closed.
 *
 * Time is measured from the attempt's own used-time counter, not from the
 * wall clock, so a student who was cut off keeps the minutes they had left.
 */
function auto_finalize_expired_attempts($db, $exam_id = null, $student_id = null)
{
    $sql = "SELECT oa.id
            FROM objective_attempts oa
            JOIN exams e ON oa.exam_id = e.id
            WHERE oa.finished_at IS NULL
            AND (
                (e.duration_minutes > 0 AND oa.time_used
                    + IF(oa.last_tick_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, oa.last_tick_at, NOW()) <= " . QE_TICK_GAP . ",
                         TIMESTAMPDIFF(SECOND, oa.last_tick_at, NOW()), 0)
                    >= e.duration_minutes * 60 + 30)
                OR (e.end_time IS NOT NULL AND NOW() > DATE_ADD(e.end_time, INTERVAL 2 MINUTE))
            )";
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
    $closed = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $attempt_id) {
        if (qe_finalize_attempt($db, (int) $attempt_id)) {
            $closed++;
        }
    }
    return $closed;
}
