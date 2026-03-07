<?php
require 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['student_id'];
$action = $_GET['action'] ?? '';

try {
    if ($action === 'save_answer') {
        $exam_id = intval($_POST['exam_id']);
        $question_id = intval($_POST['question_id']);
        $attempt_no = intval($_POST['attempt_no'] ?? 1);
        $option = $_POST['option'];
        $now = date('Y-m-d H:i:s');

        // Remove existing answer for this specific attempt
        $db->prepare("DELETE FROM objective_answers WHERE exam_id=? AND student_id=? AND question_id=? AND attempt_no=?")
            ->execute([$exam_id, $student_id, $question_id, $attempt_no]);

        // Insert new answer
        $stmt = $db->prepare("INSERT INTO objective_answers (exam_id, student_id, attempt_no, question_id, selected_option, submitted_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$exam_id, $student_id, $attempt_no, $question_id, $option, $now]);

        echo json_encode(['success' => true]);
    } elseif ($action === 'start_attempt') {
        $exam_id = intval($_POST['exam_id']);

        // Check if attempt already exists
        $stmt = $db->prepare("SELECT started_at FROM objective_attempts WHERE exam_id=? AND student_id=?");
        $stmt->execute([$exam_id, $student_id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $stmt = $db->prepare("INSERT INTO objective_attempts (exam_id, student_id, attempt_no, started_at) VALUES (?, ?, 1, NOW())");
            $stmt->execute([$exam_id, $student_id]);
            echo json_encode(['success' => true, 'started_at' => date('Y-m-d H:i:s')]);
        } else {
            echo json_encode(['success' => true, 'started_at' => $existing['started_at']]);
        }
    } elseif ($action === 'get_remaining_time') {
        $exam_id = intval($_GET['exam_id']);

        $stmt = $db->prepare("SELECT e.duration_minutes, a.started_at FROM exams e JOIN objective_attempts a ON e.id = a.exam_id WHERE e.id=? AND a.student_id=? ORDER BY a.attempt_no DESC LIMIT 1");
        $stmt->execute([$exam_id, $student_id]);
        $data = $stmt->fetch();

        if ($data) {
            $duration_seconds = $data['duration_minutes'] * 60;
            $start_timestamp = strtotime($data['started_at']);
            $current_timestamp = time();
            $elapsed = $current_timestamp - $start_timestamp;
            $remaining = max(0, $duration_seconds - $elapsed);

            echo json_encode(['success' => true, 'remaining_seconds' => $remaining]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Attempt not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
