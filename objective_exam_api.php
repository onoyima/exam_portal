<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function api_out(array $data): void
{
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['student_id'])) {
    http_response_code(401);
    api_out(['success' => false, 'error' => 'Unauthorized', 'auth' => false]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
}

$student_id = (int) $_SESSION['student_id'];
// Nothing below writes to the session; releasing the lock lets this student's
// heartbeat and answer requests run in parallel instead of queueing.
session_write_close();

$action = $_GET['action'] ?? '';
$exam_id = (int) ($_REQUEST['exam_id'] ?? 0);
$attempt_no = (int) ($_REQUEST['attempt_no'] ?? 1);

try {
    $st = $db->prepare("SELECT e.*, oa.id AS attempt_id, oa.finished_at, oa.is_locked, oa.question_order, oa.time_used, oa.started_at
        FROM exams e
        LEFT JOIN objective_attempts oa ON oa.exam_id = e.id AND oa.student_id = ? AND oa.attempt_no = ?
        WHERE e.id = ? AND e.deleted_at IS NULL");
    $st->execute([$student_id, $attempt_no, $exam_id]);
    $ctx = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ctx) {
        api_out(['success' => false, 'error' => 'Exam not found']);
    }

    if ($action === 'save_answer' || $action === 'save_answers') {
        if (!$ctx['attempt_id']) {
            api_out(['success' => false, 'error' => 'Attempt not found']);
        }
        if ($ctx['finished_at']) {
            api_out(['success' => false, 'error' => 'This attempt has already been submitted.', 'finished' => true]);
        }
        if ($ctx['is_locked']) {
            api_out(['success' => false, 'error' => 'This attempt is locked.', 'locked' => true]);
        }
        if (!empty($ctx['end_time']) && time() > strtotime($ctx['end_time']) + 120) {
            api_out(['success' => false, 'error' => 'Exam access period has ended.', 'closed' => true]);
        }
        $duration = (int) $ctx['duration_minutes'] * 60;
        if ($duration > 0 && (int) $ctx['time_used'] > $duration + 60) {
            api_out(['success' => false, 'error' => 'Time is up for this attempt.', 'time_up' => true]);
        }

        if ($action === 'save_answer') {
            $incoming = [(int) ($_POST['question_id'] ?? 0) => (string) ($_POST['option'] ?? '')];
        } else {
            $incoming = json_decode((string) ($_POST['answers'] ?? ''), true);
            if (!is_array($incoming)) {
                api_out(['success' => false, 'error' => 'Bad request']);
            }
        }

        $questions = qe_load_questions($db, $exam_id);
        $allowed = array_flip(qe_attempt_question_ids(['question_order' => $ctx['question_order']], $questions));
        $saved = [];
        $rejected = [];
        $db->beginTransaction();
        foreach ($incoming as $qid => $raw) {
            $qid = (int) $qid;
            if (!isset($allowed[$qid]) || !is_scalar($raw)) {
                $rejected[] = $qid;
                continue;
            }
            if (qe_save_answer($db, $questions[$qid], $exam_id, $student_id, $attempt_no, $raw)) {
                $saved[] = $qid;
            } else {
                $rejected[] = $qid;
            }
        }
        $db->commit();
        api_out(['success' => true, 'saved' => $saved, 'rejected' => $rejected]);
    }

    if ($action === 'get_remaining_time') {
        if (!$ctx['attempt_id']) {
            api_out(['success' => false, 'error' => 'Attempt not found']);
        }
        $rem = qe_remaining_seconds($ctx, $ctx);
        api_out(['success' => true, 'remaining_seconds' => $rem, 'untimed' => $rem === null]);
    }

    api_out(['success' => false, 'error' => 'Invalid action']);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('objective_exam_api: ' . $e->getMessage());
    http_response_code(500);
    api_out(['success' => false, 'error' => 'Server busy, retrying.']);
}
