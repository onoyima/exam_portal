<?php
namespace Lms\Core;

abstract class Service
{
    protected ?Logger $logger = null;

    public function __construct()
    {
        // Services can be initialized with dependencies via constructor injection
    }

    protected function dispatch(string $event, mixed $payload = null): void
    {
        Event::dispatch($event, $payload);
    }

    protected function cache(): Cache
    {
        return Cache::instance();
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Logger::$level($message, $context);
    }
}
