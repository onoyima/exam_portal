<?php
require_once __DIR__ . '/../db_exeat2.php';

header('Content-Type: application/json');

if (!$exeat2_db) {
    echo json_encode([]);
    exit;
}

$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;

if ($department_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $exeat2_db->prepare("
        SELECT cs.id, cs.name
        FROM course_studies cs
        WHERE cs.department_id = ?
        ORDER BY cs.name
    ");
    $stmt->execute([$department_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode([]);
}
