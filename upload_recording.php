<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'chunk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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

if ($action === 'list') {
    $attempt_id = intval($_GET['attempt_id'] ?? 0);
    $dir = "uploads/recordings/attempt_{$attempt_id}";
    $files = glob($dir . "/*.webm") ?: [];
    sort($files);
    echo json_encode(['success' => true, 'files' => array_values($files)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
