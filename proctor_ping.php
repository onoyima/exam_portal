<?php
require 'auth.php';
check_auth('student');
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
}

$student_id = $_SESSION['student_id'];
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : 'active';
$violation_type = isset($_POST['violation_type']) ? $_POST['violation_type'] : null;
$details = isset($_POST['details']) ? $_POST['details'] : null;
$attempt_no = isset($_POST['attempt_no']) ? intval($_POST['attempt_no']) : 1;

// Status should be one of: active, blur, disconnected, submitted
if (!in_array($status, ['active', 'blur', 'disconnected', 'submitted'])) {
    $status = 'active';
}

// BUG FIX: $stored_session was previously undefined — fetch from DB now
$session_stmt = $db->prepare("SELECT current_session_id FROM students WHERE id = ?");
$session_stmt->execute([$student_id]);
$stored_session = $session_stmt->fetchColumn() ?: null;

if ($stored_session !== session_id() && $stored_session !== null) {
    echo json_encode(['status' => 'FORCED_LOGOUT', 'message' => 'This account has been logged in on another device.']);
    exit;
}

// 1b. Check if Attempt is Finished
$stmt = $db->prepare("SELECT finished_at FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
$stmt->execute([$exam_id, $student_id, $attempt_no]);
$finished_at = $stmt->fetchColumn();

if ($finished_at) {
    echo json_encode(['status' => 'SUBMITTED', 'message' => 'Your assessment has been submitted.']);
    exit;
}

// 2. Update Heartbeat, Snapshot, and Proctor Status
$snapshot_base64 = isset($_POST['webcam_snapshot']) ? $_POST['webcam_snapshot'] : null;
$snapshot_path = null;

if ($snapshot_base64 && strpos($snapshot_base64, 'data:image') === 0) {
    try {
        // Process Base64 to File
        $data = explode(',', $snapshot_base64);
        if (isset($data[1])) {
            $decoded = base64_decode($data[1]);
            if ($decoded) {
                $dir = "uploads/snapshots";
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $filename = "snap_" . $student_id . "_" . $exam_id . ".jpg";
                $full_path = $dir . "/" . $filename;
                if (file_put_contents($full_path, $decoded)) {
                    $snapshot_path = $full_path;
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail if file write fails, continue with status update
        error_log("Snapshot save error: " . $e->getMessage());
    }
}

// Update DB - Using path instead of blob for performance
if ($snapshot_path) {
    // Clear the binary blob (webcam_snapshot) and use the path instead
    $update = $db->prepare("UPDATE objective_attempts SET last_active = NOW(), proctor_status = ?, snapshot_path = ?, webcam_snapshot = NULL WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND finished_at IS NULL");
    $update->execute([$status, $snapshot_path, $exam_id, $student_id, $attempt_no]);
} else {
    $update = $db->prepare("UPDATE objective_attempts SET last_active = NOW(), proctor_status = ? WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND finished_at IS NULL");
    $update->execute([$status, $exam_id, $student_id, $attempt_no]);
}

// 2b. Check for Admin Warnings
$warn_stmt = $db->prepare("SELECT warning_message, last_warning_at FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND last_warning_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
$warn_stmt->execute([$exam_id, $student_id, $attempt_no]);
$warning = $warn_stmt->fetch(PDO::FETCH_ASSOC);

$warning_to_send = null;
if ($warning && !empty($warning['warning_message'])) {
    $warning_to_send = $warning['warning_message'];
    // Clear warning after retrieval so it doesn't pop up again unless set again by examiner
    $clear_stmt = $db->prepare("UPDATE objective_attempts SET warning_message = NULL, last_warning_at = NULL WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $clear_stmt->execute([$exam_id, $student_id, $attempt_no]);
}

// 3. Log Violation if sent
if ($violation_type) {
    // Log in violations table
    $v_stmt = $db->prepare("INSERT INTO exam_violations (attempt_id, student_id, exam_id, violation_type, details) 
                            SELECT id, ?, ?, ?, ? FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $v_stmt->execute([$student_id, $exam_id, $violation_type, $details, $exam_id, $student_id, $attempt_no]);

    // Increment violation count in attempts table
    $inc_stmt = $db->prepare("UPDATE objective_attempts SET violation_count = violation_count + 1 WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND finished_at IS NULL");
    $inc_stmt->execute([$exam_id, $student_id, $attempt_no]);

    // Check total violations or if record is already locked
    $check_stmt = $db->prepare("SELECT violation_count, is_locked FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $check_stmt->execute([$exam_id, $student_id, $attempt_no]);
    $attempt_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $count = $attempt_record['violation_count'] ?? 0;
    $is_db_locked = $attempt_record['is_locked'] ?? 0;

    if ($count >= 5 || $is_db_locked) {
        // Phase 4: Enforce persistent lock
        if (!$is_db_locked) {
            $db->prepare("UPDATE objective_attempts SET is_locked = 1 WHERE exam_id = ? AND student_id = ? AND attempt_no = ?")->execute([$exam_id, $student_id, $attempt_no]);
        }
        echo json_encode(['status' => 'LOCKED', 'message' => 'Your exam interface has been locked due to repeated security violations. Please contact the administrator.', 'count' => $count]);
        exit;
    }
} else {
    // Even if no violation was just sent, check if it's already locked from a previous ping or manual lock
    $check_stmt = $db->prepare("SELECT violation_count, is_locked FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $check_stmt->execute([$exam_id, $student_id, $attempt_no]);
    $attempt_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
    if ($attempt_record && ($attempt_record['is_locked'] || $attempt_record['violation_count'] >= 5)) {
        echo json_encode(['status' => 'LOCKED', 'message' => 'Your exam interface is locked.', 'count' => $attempt_record['violation_count']]);
        exit;
    }
}

// Get current violation count and live request status
$count_stmt = $db->prepare("SELECT violation_count, is_live_requested FROM objective_attempts WHERE id = (SELECT id FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND finished_at IS NULL LIMIT 1)");
$count_stmt->execute([$exam_id, $student_id, $attempt_no]);
$attempt_info = $count_stmt->fetch(PDO::FETCH_ASSOC);
$current_count = $attempt_info['violation_count'] ?? 0;
$is_live_requested = $attempt_info['is_live_requested'] ?? 0;

// 4. Remote Command Check (Admin-to-Student)
$remote_cmd = null;
$cmd_stmt = $db->prepare("SELECT remote_command FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND finished_at IS NULL");
$cmd_stmt->execute([$exam_id, $student_id, $attempt_no]);
$cmd_res = $cmd_stmt->fetch(PDO::FETCH_ASSOC);

if ($cmd_res && $cmd_res['remote_command']) {
    $remote_cmd = $cmd_res['remote_command'];
    // Clear command immediately after consumption
    $db->prepare("UPDATE objective_attempts SET remote_command = NULL WHERE exam_id = ? AND student_id = ? AND attempt_no = ?")->execute([$exam_id, $student_id, $attempt_no]);
}

echo json_encode([
    'status' => 'OK',
    'violation_count' => $current_count,
    'is_live_requested' => (int) $is_live_requested,
    'warning' => $warning_to_send,
    'remote_command' => $remote_cmd,
    'time_ping' => date('H:i:s')
]);
