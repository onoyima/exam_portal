<?php
/**
 * Diagnostic: show the remote exam_students table definition.
 * Run from the CLI with CHECK_DB_HOST, CHECK_DB_NAME, CHECK_DB_USER and
 * CHECK_DB_PASS set in the environment.
 */
require_once __DIR__ . '/includes/cli_only.php';

$host = getenv('CHECK_DB_HOST') ?: '';
$name = getenv('CHECK_DB_NAME') ?: '';
$user = getenv('CHECK_DB_USER') ?: '';
$pass = getenv('CHECK_DB_PASS') ?: '';

if ($host === '' || $name === '' || $user === '') {
    fwrite(STDERR, "Set CHECK_DB_HOST, CHECK_DB_NAME, CHECK_DB_USER and CHECK_DB_PASS before running this script.\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port=3306;dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$r = $pdo->query('SHOW CREATE TABLE exam_students')->fetchAll(PDO::FETCH_ASSOC);
print_r($r);
