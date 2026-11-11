<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$r = $pdo->query('SELECT COUNT(*) FROM exam_students WHERE exam_id = 138')->fetchColumn();
file_put_contents('check_result.txt', "Enrolled: $r\n", FILE_APPEND);

$r = $pdo->query('SELECT COUNT(*) FROM students WHERE deleted_at IS NULL')->fetchColumn();
file_put_contents('check_result.txt', "Total: $r\n", FILE_APPEND);

$r = $pdo->query('SELECT id, matric_no FROM students WHERE deleted_at IS NULL LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('check_result.txt', print_r($r, true), FILE_APPEND);