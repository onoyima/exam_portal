<?php
session_start();
require 'db.php';

// Only allow examiner
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'examiner') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$examiner = $db->prepare("SELECT id FROM examiners WHERE username=?");
$examiner->execute([$_SESSION['user']]);
$examiner_id = $examiner->fetchColumn();

$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$term = $_GET['term'] ?? '';

if (!$selected_exam || !$term) {
    echo json_encode([]);
    exit;
}

// Verify examiner assigned to this exam
$check = $db->prepare("SELECT COUNT(*) FROM exam_examiners WHERE exam_id=? AND examiner_id=?");
$check->execute([$selected_exam, $examiner_id]);
if (!$check->fetchColumn()) {
    echo json_encode([]);
    exit;
}

$termLike = "%$term%";
$stmt = $db->prepare("
    SELECT s.id, s.name, s.username, s.matric_no 
    FROM students s 
    JOIN exam_students es ON s.id = es.student_id
    WHERE es.exam_id = ? 
      AND (s.name LIKE ? OR s.username LIKE ? OR s.matric_no LIKE ?)
    ORDER BY s.name
    LIMIT 10
");
$stmt->execute([$selected_exam, $termLike, $termLike, $termLike]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['id'],
        'label' => $row['name'] . " (" . $row['matric_no'] . ")",
        'value' => $row['name']
    ];
}

header('Content-Type: application/json');
echo json_encode($results);
