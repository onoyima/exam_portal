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

/**
 * Appends one MediaRecorder slice to a recording segment. A segment is one
 * continuous recording (one exam page session); slices only play when joined
 * in order, so they are appended to a single file instead of saved apart.
 */
function rec_append(int $attempt_id, string $prefix): array
{
    $seg = preg_replace('/[^0-9]/', '', (string) ($_POST['segment'] ?? ''));
    $seq = (int) ($_POST['seq'] ?? -1);
    if ($seg === '' || $seq < 0 || !isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Missing params'];
    }
    if ($_FILES['chunk']['size'] > 20 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Chunk too large'];
    }
    $dir = __DIR__ . "/uploads/recordings/attempt_{$attempt_id}";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = "$dir/{$prefix}_{$seg}.webm";
    $state = "$file.seq";
    $fh = fopen($state, 'c+');
    flock($fh, LOCK_EX);
    $expected = (int) stream_get_contents($fh);
    if ($seq !== $expected) {
        flock($fh, LOCK_UN);
        fclose($fh);
        // already have it (retry) or out of order: tell the client where we are
        return ['success' => $seq < $expected, 'next' => $expected];
    }
    file_put_contents($file, file_get_contents($_FILES['chunk']['tmp_name']), FILE_APPEND);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) ($expected + 1));
    flock($fh, LOCK_UN);
    fclose($fh);
    return ['success' => true, 'next' => $expected + 1];
}

if ($action === 'chunk' && $is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = intval($_POST['attempt_id'] ?? 0);
    if (!$attempt_id) {
        echo json_encode(['success' => false, 'error' => 'Missing params']);
        exit;
    }
    session_write_close();
    echo json_encode(rec_append($attempt_id, 'proctor'));
    exit;
}

if ($action === 'student_chunk' && $is_student && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = intval($_POST['attempt_id'] ?? 0);
    $student_id = $_SESSION['student_id'] ?? 0;
    if (!$attempt_id || !$student_id) {
        echo json_encode(['success' => false, 'error' => 'Missing params']);
        exit;
    }

    // Verify this attempt belongs to this student
    $check = $db->prepare("SELECT id FROM objective_attempts WHERE id = ? AND student_id = ? AND (finished_at IS NULL OR finished_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE))");
    $check->execute([$attempt_id, $student_id]);
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Attempt not found or access denied']);
        exit;
    }

    session_write_close();
    echo json_encode(rec_append($attempt_id, 'student'));
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
