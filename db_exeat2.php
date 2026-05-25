<?php
require_once __DIR__ . '/config.php';

$exeat2_db = null;
$exeat2_error = '';

// Determine if .env was loaded (vs fallback defaults)
$env_loaded = file_exists(__DIR__ . '/.env');

// Check if exeat2 constants came from .env (vs config.php fallbacks)
$exeat2_configured = $env_loaded && defined('EXEAT2_DB_HOST') && EXEAT2_DB_HOST !== '';

if (!$env_loaded) {
    $exeat2_error = ".env file not found. Create it with EXEAT2_DB_* settings.";
} elseif (!$exeat2_configured) {
    $exeat2_error = "EXEAT2_DB_HOST is empty or .env is missing required EXEAT2_DB_* values.";
} elseif (!in_array('mysql', PDO::getAvailableDrivers())) {
    $exeat2_error = "PDO MySQL driver not installed.";
} else {
    try {
        $exeat2_db = new PDO(
            "mysql:host=" . EXEAT2_DB_HOST . ";port=" . EXEAT2_DB_PORT . ";dbname=" . EXEAT2_DB_NAME . ";charset=" . EXEAT2_DB_CHARSET,
            EXEAT2_DB_USER,
            defined('EXEAT2_DB_PASS') ? EXEAT2_DB_PASS : ''
        );
        $exeat2_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $exeat2_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $exeat2_db->setAttribute(PDO::MYSQL_ATTR_MAX_BUFFER_SIZE, 10 * 1024 * 1024); // 10MB for passport blobs
    } catch (PDOException $e) {
        $exeat2_error = $e->getMessage();
        error_log("exeat2 connection failed: " . $e->getMessage());
    }
}
