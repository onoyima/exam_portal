<?php
/**
 * db.php — Primary Database Connection
 * Credentials loaded from config.php (single source of truth).
 */
require_once __DIR__ . '/config.php';

// Runtime error configuration: never expose errors to users
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}
require_once __DIR__ . '/exam_logic.php';
qe_ensure_schema($db);
if (function_exists('enforce_single_student_session')) {
    enforce_single_student_session($db);
}
if (function_exists('enforce_single_examiner_session')) {
    enforce_single_examiner_session($db);
}