<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "=== Enrollment Check ===\n";

$r = $pdo->query("SELECT COUNT(*) FROM exam_students WHERE exam_id = 138")->fetchColumn();
echo "Enrolled in exam 138: $r\n";

$r = $pdo->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
echo "Total students: $r\n";

$r = $pdo->query("SELECT id, matric_no FROM students WHERE deleted_at IS NULL LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "\nFirst 10 students:\n";
foreach ($r as $s) {
    echo "  {$s['id']}: {$s['matric_no']}\n";
}