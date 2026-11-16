<?php
/**
 * Diagnostic: inspect the school portal (exeat2) database.
 * Run from the CLI. Credentials come from EXEAT2_DB_* in /etc/veritas-cbt/.env.
 */
require_once __DIR__ . '/includes/cli_only.php';
require_once __DIR__ . '/config.php';

if (!defined('EXEAT2_DB_HOST') || EXEAT2_DB_HOST === '') {
    fwrite(STDERR, "EXEAT2_DB_* values are not configured in " . (VERITAS_ENV_PATH ?: '.env') . ".\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . EXEAT2_DB_HOST . ';port=' . EXEAT2_DB_PORT . ';dbname=' . EXEAT2_DB_NAME . ';charset=' . EXEAT2_DB_CHARSET,
    EXEAT2_DB_USER,
    defined('EXEAT2_DB_PASS') ? EXEAT2_DB_PASS : '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo "Tables:\n";
$r = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($r);

echo "\n--- student_academics columns ---\n";
$r = $pdo->query('DESCRIBE student_academics')->fetchAll(PDO::FETCH_ASSOC);
print_r($r);

echo "\n--- students columns ---\n";
$r = $pdo->query('DESCRIBE students')->fetchAll(PDO::FETCH_ASSOC);
print_r($r);

echo "\n--- departments/levels/programs tables ---\n";
foreach (['departments', 'levels', 'programmes', 'courses', 'faculties', 'academic_sessions', 'vu_sessions', 'vu_semesters'] as $t) {
    try {
        $r = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        echo "\n--- $t ---\n";
        print_r($r);
    } catch (Exception $e) {
    }
}

echo "\n--- Sample academic data ---\n";
$r = $pdo->query("SELECT sa.*, s.fname, s.lname FROM student_academics sa JOIN students s ON s.id=sa.student_id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($r);
