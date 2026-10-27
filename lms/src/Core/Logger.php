<?php
namespace Lms\Core;

class Logger
{
    private static string $logDir = '';

    public static function init(): void
    {
        self::$logDir = LMS_STORAGE . '/logs';
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }

        ini_set('error_log', self::$logDir . '/php_errors.log');
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (APP_ENV === 'production' && !lms_config('app.debug', false)) {
            return;
        }
        self::write('DEBUG', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (empty(self::$logDir)) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $requestId = $_SERVER['X-Request-ID'] ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? '-');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        $logLine = "[{$timestamp}] [{$level}] [{$requestId}] {$message}{$contextStr}" . PHP_EOL;

        $file = self::$logDir . '/lms-' . date('Y-m-d') . '.log';
        file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }
}
