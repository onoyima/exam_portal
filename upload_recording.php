<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_student = isset($_SESSION['role']) && $_SESSION['role'] === 'student';

if (!($is_admin || ($action === 'student_chunk' && $is_student))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($action === 'chunk' && $is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = intval($_POST['attempt_id'] ?? 0);
    if (!$attempt_id || !isset($_FILES['chunk'])) {
        echo json_encode(['success' => false, 'error' => 'Missing params']);
        exit;
    }

    $dir = "uploads/recordings/attempt_{$attempt_id}";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $filename  = $dir . "/rec_{$attempt_id}_" . time() . ".webm";
    move_uploaded_file($_FILES['chunk']['tmp_name'], $filename);

    echo json_encode(['success' => true, 'file' => $filename]);
    exit;
}

if ($action === 'student_chunk' && $is_student && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = intval($_POST['attempt_id'] ?? 0);
    $student_id = $_SESSION['student_id'] ?? 0;
    if (!$attempt_id || !$student_id || !isset($_FILES['chunk'])) {
        echo json_encode(['success' => false, 'error' => 'Missing params']);
        exit;
    }

    // Verify this attempt belongs to this student
    $check = $db->prepare("SELECT id FROM objective_attempts WHERE id = ? AND student_id = ?");
    $check->execute([$attempt_id, $student_id]);
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Attempt not found or access denied']);
        exit;
    }

    $dir = "uploads/recordings/attempt_{$attempt_id}";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $filename  = $dir . "/student_rec_{$attempt_id}_" . time() . ".webm";
    move_uploaded_file($_FILES['chunk']['tmp_name'], $filename);

    echo json_encode(['success' => true, 'file' => $filename]);
    exit;
}

if ($action === 'list') {
    $attempt_id = intval($_GET['attempt_id'] ?? 0);
    $dir = "uploads/recordings/attempt_{$attempt_id}";
    $files = glob($dir . "/*.webm") ?: [];
    sort($files);
    echo json_encode(['success' => true, 'files' => array_values($files)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
