<?php
namespace Lms\Core;

class Cache
{
    private static ?self $instance = null;
    private string $driver;
    private string $prefix;
    private array $store = [];

    public function __construct()
    {
        $config = $GLOBALS['lms_config']['cache'] ?? [];
        $this->driver = $config['driver'] ?? 'file';
        $this->prefix = $config['prefix'] ?? 'lms_';
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->prefix . $key;

        if ($this->driver === 'memory' || $this->driver === 'file') {
            if (isset($this->store[$key])) {
                $item = $this->store[$key];
                if ($item['expires'] === 0 || $item['expires'] > time()) {
                    return $item['value'];
                }
                unset($this->store[$key]);
            }
        }

        if ($this->driver === 'file') {
            return $this->fileGet($key) ?? $default;
        }

        if ($this->driver === 'redis') {
            return $this->redisGet($key) ?? $default;
        }

        return $default;
    }

    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $key = $this->prefix . $key;
        $ttl = $ttl ?? ($GLOBALS['lms_config']['cache']['ttl'] ?? 3600);

        if ($this->driver === 'memory') {
            $this->store[$key] = [
                'value'   => $value,
                'expires' => $ttl > 0 ? time() + $ttl : 0,
            ];
            return true;
        }

        if ($this->driver === 'file') {
            return $this->fileSet($key, $value, $ttl);
        }

        if ($this->driver === 'redis') {
            return $this->redisSet($key, $value, $ttl);
        }

        return false;
    }

    public function forget(string $key): bool
    {
        $key = $this->prefix . $key;
        unset($this->store[$key]);

        if ($this->driver === 'file') {
            $file = $this->cachePath($key);
            if (file_exists($file)) {
                return unlink($file);
            }
        }

        return true;
    }

    public function flush(): bool
    {
        $this->store = [];

        if ($this->driver === 'file') {
            $files = glob($this->cachePath('*'));
            foreach ($files as $file) {
                unlink($file);
            }
        }

        return true;
    }

    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    // ─── File Driver ─────────────────────────────────────────────────────────

    private function cachePath(string $key): string
    {
        $dir = LMS_STORAGE . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/' . md5($key) . '.cache';
    }

    private function fileGet(string $key): mixed
    {
        $file = $this->cachePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));
        if ($data === false) {
            return null;
        }

        if ($data['expires'] > 0 && $data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    private function fileSet(string $key, mixed $value, int $ttl): bool
    {
        $file = $this->cachePath($key);
        $data = serialize([
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ]);
        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    // ─── Redis Driver (future) ──────────────────────────────────────────────

    private function redisGet(string $key): mixed
    {
        return null; // TODO: Implement Redis
    }

    private function redisSet(string $key, mixed $value, int $ttl): bool
    {
        return false; // TODO: Implement Redis
    }
}
