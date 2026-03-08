<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$attempt_id = intval($_POST['attempt_id'] ?? ($_GET['attempt_id'] ?? 0));
$role = $_SESSION['role'] ?? (isset($_SESSION['student_id']) ? 'student' : 'unknown');

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

if ($action === 'clear') {
    $stmt = $db->prepare("DELETE FROM webrtc_signals WHERE attempt_id = ?");
    $stmt->execute([$attempt_id]);
    echo json_encode(['success' => true]);
    exit;
}
