<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
}

$action = $_GET['action'] ?? '';
$attempt_id = intval($_POST['attempt_id'] ?? ($_GET['attempt_id'] ?? 0));
$role = $_SESSION['role'] ?? '';

// Signalling carries a live camera feed, so callers must be signed in, and a
// student may only touch the attempt that belongs to them.
if (!in_array($role, ['admin', 'examiner', 'student'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($role === 'student') {
    $own = $db->prepare("SELECT 1 FROM objective_attempts WHERE id = ? AND student_id = ?");
    $own->execute([$attempt_id, $_SESSION['student_id'] ?? 0]);
    if (!$own->fetchColumn() || !in_array($action, ['send', 'get'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}
session_write_close();

if ($action === 'send') {
    $type = $_POST['type'] ?? '';
    $payload = $_POST['payload'] ?? '';
    $stmt = $db->prepare("INSERT INTO webrtc_signals (attempt_id, from_role, type, payload) VALUES (?, ?, ?, ?)");
    $stmt->execute([$attempt_id, $role, $type, $payload]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get') {
    $last_id = intval($_GET['last_id'] ?? 0);
    // If you are a student, you get signals from anyone who is NOT a student (admin/examiner)
    // If you are not a student, you get signals only from the student
    if ($role === 'student') {
        $stmt = $db->prepare("SELECT * FROM webrtc_signals WHERE attempt_id = ? AND from_role != 'student' AND id > ? ORDER BY id ASC");
        $stmt->execute([$attempt_id, $last_id]);
    } else {
        $stmt = $db->prepare("SELECT * FROM webrtc_signals WHERE attempt_id = ? AND from_role = 'student' AND id > ? ORDER BY id ASC");
        $stmt->execute([$attempt_id, $last_id]);
    }
    $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'signals' => $signals]);
    exit;
}

if ($action === 'request_live') {
    $stmt = $db->prepare("UPDATE objective_attempts SET is_live_requested = 1 WHERE id = ?");
    $stmt->execute([$attempt_id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'stop_live') {
    $stmt = $db->prepare("UPDATE objective_attempts SET is_live_requested = 0 WHERE id = ?");
    $stmt->execute([$attempt_id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'clear') {
    $stmt = $db->prepare("DELETE FROM webrtc_signals WHERE attempt_id = ?");
    $stmt->execute([$attempt_id]);
    echo json_encode(['success' => true]);
    exit;
}
