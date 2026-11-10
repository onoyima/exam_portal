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

// ============================================================
// PERFORMANCE SETTINGS FOR HIGH CONCURRENCY (1000+ students)
// ============================================================
if (!defined('HEARTBEAT_INTERVAL')) define('HEARTBEAT_INTERVAL', 30000);    // 30s (was 15s)
if (!defined('HEARTBEAT_GRACE')) define('HEARTBEAT_GRACE', 45000);         // 45s grace
if (!defined('ANSWER_FLUSH_DELAY')) define('ANSWER_FLUSH_DELAY', 2000);    // 2s batch (was 600ms)
if (!defined('ANSWER_MAX_BATCH')) define('ANSWER_MAX_BATCH', 50);
if (!defined('WEBCAM_MAX_STUDENTS')) define('WEBCAM_MAX_STUDENTS', 500);   // Auto-disable webcam > 500

// Session handling for high concurrency (Redis preferred)
if (extension_loaded('redis') && !ini_get('session.save_handler')) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', 'tcp://127.0.0.1:6379?timeout=2.5&read_timeout=2.5');
} elseif (extension_loaded('memcached') && !ini_get('session.save_handler')) {
    ini_set('session.save_handler', 'memcached');
    ini_set('session.save_path', '127.0.0.1:11211');
}

// Session GC tuning for high concurrency
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1000);
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1000);

// OPcache verification
if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status(false);
    if (empty($opcache['opcache_enabled'])) {
        error_log('WARNING: OPcache is not enabled - performance will suffer');
    }
}

// Persistent DB connections for high concurrency (use in db.php)
// PDO::ATTR_PERSISTENT => true

