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
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,           // Persistent connections for high concurrency
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]
    );
    $db->exec("SET time_zone = '" . date('P') . "'");
    // Optimize for high concurrency
    $db->exec("SET SESSION innodb_lock_wait_timeout = 5");
    $db->exec("SET SESSION net_read_timeout = 30");
    $db->exec("SET SESSION net_write_timeout = 30");
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