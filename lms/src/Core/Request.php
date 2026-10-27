<?php
namespace Lms\Core;

class Request
{
    private array $params;
    private array $query;
    private array $body;
    private array $files;
    private array $headers;
    private ?string $token = null;
    private ?array $user = null;

    public function __construct(array $params = [])
    {
        $this->params  = $params;
        $this->query   = $_GET;
        $this->files   = $_FILES;
        $this->headers = $this->parseHeaders();

        $this->body = $this->parseBody();
        $this->token = $this->extractToken();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        // Apache may not pass Authorization to $_SERVER['HTTP_AUTHORIZATION']
        // Try common fallback locations
        if (!isset($headers['Authorization'])) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $headers['Authorization'] = $_SERVER[$key];
                    break;
                }
            }
        }
        // Last resort: try Apache's getallheaders()
        if (!isset($headers['Authorization']) && function_exists('getallheaders')) {
            $all = getallheaders();
            if (!empty($all['Authorization'])) {
                $headers['Authorization'] = $all['Authorization'];
            } elseif (!empty($all['authorization'])) {
                $headers['Authorization'] = $all['authorization'];
            }
        }
        return $headers;
    }

    private function parseBody(): array
    {
        $contentType = $this->header('Content-Type', '');

        if (strpos($contentType, 'application/json') !== false) {
            $body = file_get_contents('php://input');
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        }

        if ($this->method() === 'POST') {
            return $_POST;
        }

        if (in_array($this->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $body = file_get_contents('php://input');
            parse_str($body, $parsed);
            return $parsed;
        }

        return [];
    }

    private function extractToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return $this->body('token') ?? $this->query('token') ?? null;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function body(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function file(string $key): ?array
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK
            ? $this->files[$key]
            : null;
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function setUserContext(int $id, string $uuid = '', string $email = '', ?int $portalUserId = null, ?string $portalUserType = null, ?string $role = null): void
    {
        $this->user = [
            'id' => $id,
            'uuid' => $uuid,
            'email' => $email,
            'portal_user_id' => $portalUserId,
            'portal_user_type' => $portalUserType,
            'role' => $role,
        ];
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user['id'] ?? null;
    }

    public function userRole(): ?string
    {
        return $this->user['role'] ?? null;
    }

    public function portalUserId(): ?int
    {
        $pid = $this->user['portal_user_id'] ?? null;
        return $pid ? (int) $pid : null;
    }

    public function portalUserType(): ?string
    {
        return $this->user['portal_user_type'] ?? null;
    }

    public function isStudent(): bool
    {
        return $this->portalUserType() === 'student';
    }

    public function isStaff(): bool
    {
        return in_array($this->portalUserType(), ['examiner', 'staff'], true);
    }
}
