<?php
/**
 * Next Gen LMS — Application Bootstrap
 * 
 * Handles autoloading, error handling, and environment initialization
 * for the Integrated Learning Management System.
 */

// ─── Prevent Double Loading ─────────────────────────────────────────────────
defined('LMS_BOOTSTRAPPED') || define('LMS_BOOTSTRAPPED', true);

// ─── Path Constants ─────────────────────────────────────────────────────────
define('LMS_ROOT', dirname(__DIR__) . '/lms');
define('LMS_SRC', LMS_ROOT . '/src');
define('LMS_CONFIG', LMS_ROOT . '/config');
define('LMS_PUBLIC', LMS_ROOT . '/public');
define('LMS_VIEWS', LMS_ROOT . '/views');
define('LMS_STORAGE', LMS_ROOT . '/storage');
define('LMS_RUNNING', true);

// ─── Composer Autoloader ────────────────────────────────────────────────────
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// ─── Class Autoloader (PSR-4 style) ─────────────────────────────────────────
spl_autoload_register(function (string $class) {
    $prefix = 'Lms\\';
    $baseDir = LMS_SRC . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── Error Handler ──────────────────────────────────────────────────────────
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $route = '/' . trim(str_replace($basePath, '', $uri), '/');
    $isApi = preg_match('#^/(auth|dashboard|courses|modules|lessons|enrollments|assignments|quizzes|discussions|messages|notifications|gradebook|content|analytics|exam-bridge|admin|academic|sync)(/|$)#', $route);

    http_response_code(500);

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => APP_ENV !== 'production' ? $e->getMessage() : 'An internal error occurred',
            ]
        ]);
    } else {
        $message = APP_ENV !== 'production'
            ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            : 'An unexpected error occurred. Please try again later.';
        echo '<h1>500 Internal Server Error</h1><p>' . htmlspecialchars($message) . '</p>';
    }

    error_log("[LMS Fatal] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
    exit(1);
});

// ─── Load Configuration ─────────────────────────────────────────────────────
$configFiles = [
    LMS_CONFIG . '/lms.php',
    LMS_CONFIG . '/permissions.php',
    LMS_CONFIG . '/integration.php',
    LMS_CONFIG . '/database.php',
];

$GLOBALS['lms_config'] = [];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $config = require $file;
        if (is_array($config)) {
            $GLOBALS['lms_config'] = array_replace_recursive($GLOBALS['lms_config'], $config);
        }
    }
}

// ─── Environment ────────────────────────────────────────────────────────────
if (!defined('APP_ENV')) {
    $env = $GLOBALS['lms_config']['app']['env'] ?? 'development';
    define('APP_ENV', $env);
}

// ─── Timezone ───────────────────────────────────────────────────────────────
date_default_timezone_set($GLOBALS['lms_config']['app']['timezone'] ?? 'Africa/Lagos');

// ─── Initialize Database Connections ───────────────────────────────────────
\Lms\Core\Database::initFromConfig();

// ─── Helper Functions ──────────────────────────────────────────────────────

/**
 * Get a configuration value using dot notation.
 */
function lms_config(string $key, mixed $default = null): mixed
{
    $keys = explode('.', $key);
    $value = $GLOBALS['lms_config'];

    foreach ($keys as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * Quick debug helper (development only).
 */
function lms_dump(mixed ...$vars): void
{
    if (APP_ENV !== 'production') {
        echo '<pre>';
        foreach ($vars as $var) {
            print_r($var);
            echo "\n\n";
        }
        echo '</pre>';
    }
}

/**
 * Generate a UUID v4.
 */
function lms_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
