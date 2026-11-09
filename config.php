<?php
/**
 * config.php — Shared Database Configuration
 * Loads environment variables from c:\xampp\htdocs\exam_portal\.env (or .env in same directory).
 */
function loadEnvConfig($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove optional quotes surrounding values
            if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}

// Load env configurations
$env_path = __DIR__ . '/.env';
$env_loaded = file_exists($env_path);
if ($env_loaded) {
    loadEnvConfig($env_path);
} else {
    error_log("config.php: .env not found at $env_path — using fallback defaults");
}

// Set fallback defaults if not defined in .env
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'backup_db_pre_implementation');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 1800);
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Africa/Lagos');

// PHP and MySQL must agree on the clock, otherwise exam open/close times and
// remaining-time calculations drift by the difference (XAMPP ships PHP set to
// Europe/Berlin). db.php applies the same offset to the MySQL session.
if (!@date_default_timezone_set(APP_TIMEZONE)) {
    date_default_timezone_set('Africa/Lagos');
}

// exeat2 school portal DB fallbacks — only used when .env is missing
if (!defined('EXEAT2_DB_HOST')) define('EXEAT2_DB_HOST', $env_loaded ? '' : 'localhost');
if (!defined('EXEAT2_DB_PORT')) define('EXEAT2_DB_PORT', '3306');
if (!defined('EXEAT2_DB_NAME')) define('EXEAT2_DB_NAME', $env_loaded ? '' : 'exeat2');
if (!defined('EXEAT2_DB_USER')) define('EXEAT2_DB_USER', $env_loaded ? '' : 'root');
if (!defined('EXEAT2_DB_PASS')) define('EXEAT2_DB_PASS', '');
if (!defined('EXEAT2_DB_CHARSET')) define('EXEAT2_DB_CHARSET', 'utf8mb4');

