<?php
namespace Lms\Core;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];
    private string $basePath = '';

    public function __construct()
    {
        $this->basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    }

    public function addGlobalMiddleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function match(array $methods, string $path, mixed $handler, array $middleware = []): void
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middleware);
        }
    }

    private function addRoute(string $method, string $path, mixed $handler, array $middleware): void
    {
        $fullPath = $this->prefix . $path;

        $this->routes[] = [
            'method'    => $method,
            'path'      => $fullPath,
            'pattern'   => $this->pathToPattern($fullPath),
            'handler'   => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\/:([a-zA-Z_]+)/', '/(?P<$1>[^/]+)', $path);
        $pattern = preg_replace('/\/:([a-zA-Z_]+)\?/', '(?:/(?P<$1>[^/]+))?', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove base path from URI
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        $uri = '/' . trim($uri, '/');

        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $matchedRoute = $route;
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                break;
            }
        }

        if (!$matchedRoute) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Route not found'],
            ]);
            return;
        }

        // Build middleware chain
        $handler = $matchedRoute['handler'];
        $allMiddleware = array_merge($this->globalMiddleware, $matchedRoute['middleware']);

        // Create request and response
        $request = new Request($params);
        $response = new Response();

        // Run middleware pipeline
        $next = function (Request $req, Response $res) use ($handler, $params) {
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = new $class();
                $controller->setRequest($req);
                $controller->setResponse($res);
                return $controller->$method($req, $res, ...$params);
            }

            if (is_callable($handler)) {
                return $handler($req, $res, ...$params);
            }

            throw new \RuntimeException('Invalid route handler');
        };

        // Wrap middleware in reverse order
        for ($i = count($allMiddleware) - 1; $i >= 0; $i--) {
            $mw = $allMiddleware[$i];
            $currentNext = $next;
            $next = function (Request $req, Response $res) use ($mw, $currentNext) {
                return $mw($req, $res, $currentNext);
            };
        }

        // Execute
        try {
            $result = $next($request, $response);
            if ($result !== null && !$response->isSent()) {
                $response->json($result);
            }
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\Throwable $e): void
    {
        $code = $e->getCode();
        if (!is_int($code) || $code < 400 || $code > 599) {
            $code = 500;
        }

        http_response_code($code);
        header('Content-Type: application/json');

        $error = [
            'success' => false,
            'error'   => [
                'code'    => $this->getErrorCode($e),
                'message' => $e->getMessage(),
            ],
        ];

        if (APP_ENV === 'development') {
            $error['error']['file'] = $e->getFile();
            $error['error']['line'] = $e->getLine();
            $error['error']['trace'] = $e->getTraceAsString();
        }

        echo json_encode($error);
        \Lms\Core\Logger::error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'uri'  => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }

    private function getErrorCode(\Throwable $e): string
    {
        $map = [
            'AuthenticationException' => 'AUTH_ERROR',
            'AuthorizationException'  => 'FORBIDDEN',
            'ValidationException'     => 'VALIDATION_ERROR',
            'NotFoundException'       => 'NOT_FOUND',
            'ConflictException'       => 'CONFLICT',
            'IntegrationException'    => 'INTEGRATION_ERROR',
        ];

        $class = (new \ReflectionClass($e))->getShortName();
        return $map[$class] ?? 'INTERNAL_ERROR';
    }
}
