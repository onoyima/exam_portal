<?php
namespace Lms\Services;

use Lms\Core\Logger;

class RedisCacheService
{
    private static ?\Redis $redis = null;
    private static bool $available = false;

    public static function init(): void
    {
        if (!extension_loaded('redis')) {
            Logger::warning('Redis extension not loaded');
            return;
        }

        $host = lms_config('cache.redis.host', '127.0.0.1');
        $port = (int) lms_config('cache.redis.port', 6379);
        $password = lms_config('cache.redis.password');
        $database = (int) lms_config('cache.redis.database', 0);

        try {
            $redis = @new \Redis();
            if (!$redis->connect($host, $port, 2.5)) {
                Logger::error('Redis connection failed', ['host' => $host, 'port' => $port]);
                return;
            }

            if ($password !== null && $password !== '') {
                if (!$redis->auth($password)) {
                    Logger::error('Redis authentication failed');
                    return;
                }
            }

            $redis->select($database);
            self::$redis = $redis;
            self::$available = true;

            Logger::info('Redis connected', ['host' => $host, 'port' => $port, 'database' => $database]);
        } catch (\Throwable $e) {
            Logger::error('Redis initialization failed: ' . $e->getMessage());
            self::$redis = null;
            self::$available = false;
        }
    }

    public static function connection(): ?\Redis
    {
        return self::$redis;
    }

    public static function get(string $key): mixed
    {
        if (!self::available()) return null;

        try {
            $prefix = lms_config('cache.redis.prefix', 'lms:');
            $value = self::$redis->get($prefix . $key);
            if ($value === false) return null;

            $unserialized = @unserialize($value);
            return $unserialized !== false ? $unserialized : $value;
        } catch (\Throwable $e) {
            Logger::error('Redis get failed: ' . $e->getMessage(), ['key' => $key]);
            return null;
        }
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!self::available()) return false;

        try {
            $prefix = lms_config('cache.redis.prefix', 'lms:');
            $serialized = serialize($value);
            return self::$redis->setex($prefix . $key, $ttl, $serialized);
        } catch (\Throwable $e) {
            Logger::error('Redis set failed: ' . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    public static function delete(string $key): bool
    {
        if (!self::available()) return false;

        try {
            $prefix = lms_config('cache.redis.prefix', 'lms:');
            return self::$redis->del($prefix . $key) > 0;
        } catch (\Throwable $e) {
            Logger::error('Redis delete failed: ' . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    public static function flush(): bool
    {
        if (!self::available()) return false;

        try {
            $prefix = lms_config('cache.redis.prefix', 'lms:');
            $keys = self::$redis->keys($prefix . '*');
            if (!empty($keys)) {
                self::$redis->del($keys);
            }
            return true;
        } catch (\Throwable $e) {
            Logger::error('Redis flush failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function ping(): bool
    {
        if (!self::available()) return false;

        try {
            return self::$redis->ping() !== false;
        } catch (\Throwable $e) {
            Logger::error('Redis ping failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function available(): bool
    {
        if (!self::$available || self::$redis === null) return false;

        try {
            return self::$redis->isConnected();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
