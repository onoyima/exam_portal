<?php
namespace Lms\Core;

class Event
{
    private static array $listeners = [];
    private static array $wildcardListeners = [];

    public static function listen(string $event, callable $listener): void
    {
        if (strpos($event, '*') !== false) {
            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($event, '/')) . '$/';
            self::$wildcardListeners[$pattern][] = $listener;
        } else {
            self::$listeners[$event][] = $listener;
        }
    }

    public static function dispatch(string $event, mixed $payload = null): void
    {
        $logger = Logger::class;

        // Dispatch exact listeners
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $listener) {
                try {
                    $listener($payload, $event);
                } catch (\Throwable $e) {
                    Logger::error("Event listener failed for {$event}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Dispatch wildcard listeners
        foreach (self::$wildcardListeners as $pattern => $listeners) {
            if (preg_match($pattern, $event)) {
                foreach ($listeners as $listener) {
                    try {
                        $listener($payload, $event);
                    } catch (\Throwable $e) {
                        Logger::error("Wildcard event listener failed for {$event}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Log event in debug mode
        if (APP_ENV !== 'production') {
            Logger::debug("Event dispatched: {$event}", [
                'payload' => json_encode($payload),
            ]);
        }
    }

    public static function remove(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    public static function clear(): void
    {
        self::$listeners = [];
        self::$wildcardListeners = [];
    }
}
