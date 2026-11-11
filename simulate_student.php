<?php
/**
 * Simulate a single student taking an exam
 * Called by load_test.php
 * 
 * Usage: php simulate_student.php <exam_id> <duration_seconds> <students_json>
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if ($argc < 4) {
    die("Usage: php simulate_student.php <exam_id> <duration_seconds> <students_json>\n");
}

$examId = (int)$argv[1];
$durationSeconds = (int)$argv[2];
$students = json_decode($argv[3], true);

if (!$students || !is_array($students)) {
    die("Invalid students JSON\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$examId = (int)$argv[1];
$durationSeconds = (int)$argv[2];

// Get exam details
$exam = $db->prepare("SELECT id, name, duration_minutes FROM exams WHERE id = ? AND deleted_at IS NULL AND is_active = 1");
$exam->execute([$examId]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Exam not found\n");
}

// Get questions for this exam
$questions = $db->prepare("SELECT id, question_type, mark FROM objective_questions WHERE exam_id = ? AND deleted_at IS NULL");
$questions->execute([$examId]);
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("No questions for exam\n");
}

$startTime = time();
$endTime = $startTime + $durationSeconds;

$results = [
    'requests' => 0,
    'success' => 0,
    'failed' => 0,
    'times' => [],
    'errors' => [],
];

$pdo = $db; // PDO instance

foreach ($students as $student) {
    $studentId = $student['id'];
    $matricNo = $student['matric_no'];
    
    try {
        // 1. Start attempt
        $attemptId = null;
        $attemptNo = 1;
        
        $st = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id = ? AND student_id = ? ORDER BY attempt_no DESC LIMIT 1");
        $st->execute([$examId, $student['id']]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !$existing['finished_at']) {
            $attemptId = $existing['id'];
            $attemptNo = $existing['attempt_no'];
        } else {
            $attemptNo = ($existing ? $existing['attempt_no'] + 1 : 1);
            if ($attemptNo > 3) continue; // Max attempts
            
            $st = $db->prepare("INSERT INTO objective_attempts (exam_id, student_id, login_id, attempt_no, started_at, time_used, last_tick_at) VALUES (?, ?, ?, ?, NOW(), 0, NOW())");
            $st->execute([$examId, $student['id'], $student['id'], $attemptNo]);
            $attemptId = $db->lastInsertId();
        }
        
        // Simulate answering questions
        $questionsToAnswer = rand(1, min(10, count($questions)));
        $answeredQuestions = array_rand(array_flip(range(0, count($questions) - 1)), min($questionsToAnswer, count($questions)));
        
        foreach ($answeredQuestions as $qIndex) {
            $q = $questions[$qIndex];
            $answer = '';
            
            switch ($q['question_type']) {
                case 'mcq_single':
                    $answer = chr(65 + rand(0, 3)); // A, B, C, D
                    break;
                case 'mcq_multi':
                    $num = rand(1, 3);
                    $options = range('A', 'D');
                    shuffle($options);
                    $answer = implode(',', array_slice($options, 0, $num));
                    break;
                case 'true_false':
                    $answer = rand(0, 1) ? 'A' : 'B';
                    break;
                case 'mtf':
                    $stmtCount = 5; // assume 5 statements
                    $answer = '';
                    for ($i = 0; $i < $stmtCount; $i++) {
                        $answer .= rand(0, 1) ? 'T' : 'F';
                    }
                    break;
            }
            
            // Save answer
            $start = microtime(true);
            $st = $db->prepare("INSERT INTO objective_answers (attempt_id, question_id, selected_option, attempt_no) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option)");
            $success = $st->execute([$attemptId, $q['id'], $answer, $attemptNo]);
            $results['times'][] = (microtime(true) - $start) * 1000;
            
            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            $results['requests']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Student {$student['matric_no']}: " . $e->getMessage();
            $results['failed']++;
        }
    }
    
    // Finalize attempt
    try {
        $db->prepare("UPDATE objective_attempts SET finished_at = NOW(), time_used = TIMESTAMPDIFF(SECOND, started_at, NOW()) WHERE id = ?")->execute([$attemptId]);
    } catch (Exception $e) {
        // Ignore
    }
}

// Output results as JSON
$output = [
    'requests' => $results['requests'],
    'success' => $results['success'],
    'failed' => $results['failed'],
    'times' => $results['times'],
    'errors' => $results['errors'],
];

echo "RESULT: " . json_encode($results) . "\n";