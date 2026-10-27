<?php
namespace Lms\Core;

class Database
{
    private static array $connections = [];
    private static array $configs = [];

    public static function configure(string $name, array $config): void
    {
        self::$configs[$name] = $config;
    }

    public static function connection(string $name = 'lms'): \PDO
    {
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $config = self::$configs[$name] ?? self::loadConfig($name);

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connections[$name] = new \PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['charset']}_unicode_ci",
            ]
        );

        return self::$connections[$name];
    }

    public static function prefix(string $name = 'lms'): string
    {
        $config = self::$configs[$name] ?? self::loadConfig($name);
        return $config['prefix'] ?? 'lms_';
    }

    public static function disconnect(string $name = null): void
    {
        if ($name) {
            unset(self::$connections[$name]);
        } else {
            self::$connections = [];
        }
    }

    public static function initFromConfig(): void
    {
        $databases = $GLOBALS['lms_config']['database'] ?? [];
        foreach ($databases as $name => $config) {
            if (!empty($config['host'])) {
                self::$configs[$name] = $config;
            }
        }
    }

    private static function loadConfig(string $name): array
    {
        $config = $GLOBALS['lms_config']['database'][$name] ?? [];
        if (empty($config)) {
            throw new \RuntimeException("Database configuration '{$name}' not found.");
        }
        self::$configs[$name] = $config;
        return $config;
    }
}
