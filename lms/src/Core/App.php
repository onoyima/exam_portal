<?php
namespace Lms\Core;

class App
{
    private static ?App $instance = null;
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            $concrete = $this->singletons[$abstract];
            $instance = $this->resolve($concrete, $parameters);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolve($this->bindings[$abstract], $parameters);
        }

        if (class_exists($abstract)) {
            return $this->resolveClass($abstract, $parameters);
        }

        throw new \RuntimeException("Unable to resolve [{$abstract}]");
    }

    public function get(string $abstract): mixed
    {
        return $this->instances[$abstract] ?? null;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->singletons[$abstract]) || isset($this->instances[$abstract]);
    }

    private function resolve(callable|string $concrete, array $parameters): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this, ...$parameters);
        }
        return $this->resolveClass($concrete, $parameters);
    }

    private function resolveClass(string $class, array $parameters): mixed
    {
        $ref = new \ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Class [{$class}] is not instantiable");
        }
        $constructor = $ref->getConstructor();
        if (!$constructor) {
            return $ref->newInstance();
        }
        $deps = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (isset($parameters[$name])) {
                $deps[] = $parameters[$name];
            } elseif ($param->getType() && !$param->getType()->isBuiltin()) {
                $typeName = $param->getType()->getName();
                $deps[] = $this->make($typeName);
            } elseif ($param->isDefaultValueAvailable()) {
                $deps[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Unable to resolve parameter [{$name}] for [{$class}]");
            }
        }
        return $ref->newInstanceArgs($deps);
    }

    public function boot(): void
    {
        Logger::info('Application booted', [
            'name' => lms_config('app.name', 'Next Gen LMS'),
            'env' => APP_ENV,
        ]);
    }
}
