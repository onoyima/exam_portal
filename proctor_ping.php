<?php
/**
 * Exam heartbeat. Called by the exam page every ~15 seconds and whenever a
 * security event happens. It keeps the attempt's used-time counter moving,
 * records violations and hands back anything the proctor has queued for the
 * student (warnings, live-view requests, lock state).
 */
require 'auth.php';
check_auth('student');
require 'db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
}

$student_id = (int) $_SESSION['student_id'];
$my_session = session_id();
session_write_close();

$exam_id = (int) ($_POST['exam_id'] ?? 0);
$attempt_no = (int) ($_POST['attempt_no'] ?? 1);
$status = in_array($_POST['status'] ?? '', ['active', 'blur', 'disconnected', 'submitted'], true) ? $_POST['status'] : 'active';
$violation_type = isset($_POST['violation_type']) ? substr(preg_replace('/[^A-Z0-9_]/', '', strtoupper($_POST['violation_type'])), 0, 50) : null;
$details = isset($_POST['details']) ? substr((string) $_POST['details'], 0, 500) : null;
$client_elapsed = (int) ($_POST['client_elapsed'] ?? 0);
const MAX_VIOLATIONS = 5;

function ping_out(array $data): void
{
    echo json_encode($data + ['time_ping' => date('H:i:s')]);
    exit;
}

try {
    $st = $db->prepare("SELECT oa.id, oa.finished_at, oa.time_used, oa.violation_count, oa.is_locked,
               s.current_session_id, e.duration_minutes, e.end_time
        FROM objective_attempts oa
        JOIN students s ON s.id = oa.student_id
        JOIN exams e ON e.id = oa.exam_id
        WHERE oa.exam_id = ? AND oa.student_id = ? AND oa.attempt_no = ?");
    $st->execute([$exam_id, $student_id, $attempt_no]);
    $att = $st->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        ping_out(['status' => 'NO_ATTEMPT']);
    }
    if ($att['current_session_id'] !== null && $att['current_session_id'] !== $my_session) {
        ping_out(['status' => 'FORCED_LOGOUT', 'message' => 'This account has been logged in on another device.']);
    }
    if ($att['finished_at']) {
        ping_out(['status' => 'SUBMITTED', 'message' => 'Your assessment has been submitted.']);
    }
    $attempt_id = (int) $att['id'];

    if ($att['is_locked']) {
        // A locked student is waiting for the invigilator: keep the clock
        // still, but stay in touch so an unlock is picked up straight away.
        $db->prepare("UPDATE objective_attempts SET last_tick_at = NOW(), last_active = NOW(), proctor_status = 'blur' WHERE id = ?")
            ->execute([$attempt_id]);
        ping_out(['status' => 'LOCKED', 'message' => 'Your exam interface is locked. Please contact the invigilator.', 'count' => (int) $att['violation_count']]);
    }

    // Webcam thumbnail goes to disk; the row only keeps the path.
    $snapshot_path = null;
    $snap = $_POST['webcam_snapshot'] ?? '';
    if ($snap !== '' && strpos($snap, 'data:image/jpeg;base64,') === 0 && strlen($snap) < 400000) {
        $bin = base64_decode(substr($snap, 23), true);
        if ($bin !== false) {
            $dir = __DIR__ . '/uploads/snapshots';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = "uploads/snapshots/snap_{$student_id}_{$exam_id}.jpg";
            if (@file_put_contents(__DIR__ . '/' . $file, $bin) !== false) {
                $snapshot_path = $file;
            }
        }
    }

    qe_tick_attempt($db, $attempt_id, $client_elapsed);
    if ($snapshot_path) {
        $db->prepare("UPDATE objective_attempts SET proctor_status = ?, snapshot_path = ?, webcam_snapshot = NULL WHERE id = ?")
            ->execute([$status, $snapshot_path, $attempt_id]);
    } else {
        $db->prepare("UPDATE objective_attempts SET proctor_status = ? WHERE id = ?")->execute([$status, $attempt_id]);
    }

    if ($violation_type) {
        $db->prepare("INSERT INTO exam_violations (attempt_id, student_id, exam_id, violation_type, details) VALUES (?, ?, ?, ?, ?)")
            ->execute([$attempt_id, $student_id, $exam_id, $violation_type, $details]);
        $db->prepare("UPDATE objective_attempts SET violation_count = violation_count + 1,
                is_locked = IF(violation_count >= ?, 1, is_locked)
            WHERE id = ? AND finished_at IS NULL")
            ->execute([MAX_VIOLATIONS, $attempt_id]);
    }

    $st = $db->prepare("SELECT time_used, violation_count, is_locked, is_live_requested, warning_message, last_warning_at, remote_command
        FROM objective_attempts WHERE id = ?");
    $st->execute([$attempt_id]);
    $now = $st->fetch(PDO::FETCH_ASSOC);

    $warning = null;
    $command = $now['remote_command'] ?: null;
    if (!empty($now['warning_message']) && strtotime((string) $now['last_warning_at']) > time() - 60) {
        $warning = $now['warning_message'];
    }
    if ($warning !== null || $command !== null || !empty($now['warning_message'])) {
        $db->prepare("UPDATE objective_attempts SET warning_message = NULL, last_warning_at = NULL, remote_command = NULL WHERE id = ?")
            ->execute([$attempt_id]);
    }

    if ($now['is_locked'] || (int) $now['violation_count'] >= MAX_VIOLATIONS) {
        ping_out([
            'status' => 'LOCKED',
            'message' => 'Your exam interface has been locked due to repeated security violations. Please contact the administrator.',
            'count' => (int) $now['violation_count'],
        ]);
    }

    $remaining = qe_remaining_seconds($att, $now);
    $duration = (int) $att['duration_minutes'] * 60;
    $overshoot = $duration > 0 ? (int) $now['time_used'] - $duration : 0;
    if ($remaining === 0) {
        // The page normally submits itself at zero (so its last answers go
        // with it). If it hasn't after a short grace, close the attempt here.
        $closed_window = !empty($att['end_time']) && time() > strtotime($att['end_time']) + 60;
        if ($overshoot > 20 || $closed_window) {
            qe_finalize_attempt($db, $attempt_id);
            ping_out(['status' => 'SUBMITTED', 'message' => 'Time is up. Your answers have been submitted.']);
        }
        ping_out(['status' => 'TIME_UP', 'remaining_seconds' => 0]);
    }

    ping_out([
        'status' => 'OK',
        'violation_count' => (int) $now['violation_count'],
        'is_live_requested' => (int) $now['is_live_requested'],
        'warning' => $warning,
        'remote_command' => $command,
        'remaining_seconds' => $remaining,
    ]);
} catch (Throwable $e) {
    error_log('proctor_ping: ' . $e->getMessage());
    http_response_code(500);
    ping_out(['status' => 'ERROR']);
}
