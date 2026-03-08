<?php
require 'auth.php';
check_auth('student');
require 'db.php';

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

// 1. Check for Forced Logout (Multi-device login)
$stmt = $db->prepare("SELECT current_session_id FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$stored_session = $stmt->fetchColumn();

if ($stored_session !== session_id() && $stored_session !== null) {
    echo json_encode(['status' => 'FORCED_LOGOUT', 'message' => 'This account has been logged in on another device.']);
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
                $filename = "snap_" . $student_id . "_" . $exam_id . "_" . time() . ".jpg";
                $full_path = "uploads/snapshots/" . $filename;
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
    // Clear warning after retrieval so it doesn't pop up again unless set again (optional, depending on UX choice)
    // For now, keep it showing for the 30s interval or until admin changes it.
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

    // Check total violations
    $check_stmt = $db->prepare("SELECT violation_count FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $check_stmt->execute([$exam_id, $student_id, $attempt_no]);
    $count = $check_stmt->fetchColumn();

    if ($count >= 3) {
        echo json_encode(['status' => 'TERMINATE', 'message' => 'Multiple violations detected. Your exam has been automatically submitted.', 'count' => $count]);
        exit;
    }
}

// Get current violation count and live request status
$count_stmt = $db->prepare("SELECT violation_count, is_live_requested FROM objective_attempts WHERE id = (SELECT id FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND finished_at IS NULL LIMIT 1)");
$count_stmt->execute([$exam_id, $student_id, $attempt_no]);
$attempt_info = $count_stmt->fetch(PDO::FETCH_ASSOC);
$current_count = $attempt_info['violation_count'] ?? 0;
$is_live_requested = $attempt_info['is_live_requested'] ?? 0;

echo json_encode([
    'status' => 'OK',
    'violation_count' => $current_count,
    'is_live_requested' => (int) $is_live_requested,
    'warning' => $warning_to_send,
    'time_ping' => date('H:i:s')
]);
