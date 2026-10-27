<?php
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Cache;
use Lms\Core\Logger;

/**
 * Authentication Middleware — Validates JWT token and sets user on request.
 */
class AuthMiddleware
{
    public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        $token = $req->token();

        if (!$token) {
            $res->unauthorized('Authentication token is required');
            return null;
        }

        try {
            // Validate JWT (simple HMAC-SHA256 implementation)
            $payload = $this->validateToken($token);

            if (!$payload) {
                $res->unauthorized('Invalid or expired token');
                return null;
            }

            // Load user from database
            $db = \Lms\Core\Database::connection('lms');
            $stmt = $db->prepare("
                SELECT u.*, COALESCE(r.name, u.portal_user_type) as role_name
                FROM lms_users u
                LEFT JOIN lms_user_roles ur ON u.id = ur.user_id
                LEFT JOIN lms_roles r ON ur.role_id = r.id
                WHERE u.id = ? AND u.is_active = 1 AND u.deleted_at IS NULL
            ");
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();

            if (!$user) {
                $res->unauthorized('User not found or inactive');
                return null;
            }

            // Set user on request
            $req->setUser($user);
            $req->attributes['user_id'] = $user['id'];
            $req->attributes['user_role'] = $user['role_name'];

            Logger::debug('Auth success', ['user' => $user['id'], 'role' => $user['role_name']]);

        } catch (\Throwable $e) {
            Logger::error('Auth middleware error', ['error' => $e->getMessage()]);
            $res->unauthorized('Authentication failed');
            return null;
        }

        return $next($req, $res);
    }

    private function validateToken(string $token): ?array
    {
        try {
            $secret = \lms_config('auth.jwt_secret', 'default-secret');
            return (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }
    }
}

/**
 * Role Middleware — Checks if user has required role.
 */
class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array|string $roles)
    {
        $this->allowedRoles = is_array($roles) ? $roles : [$roles];
    }

    public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        $userRole = $req->userRole();

        if (!$userRole || !in_array($userRole, $this->allowedRoles)) {
            $res->forbidden('You do not have permission to access this resource');
            return null;
        }

        return $next($req, $res);
    }
}

/**
 * Rate Limit Middleware — Simple rate limiting using cache.
 */
class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        $key = 'ratelimit:' . $req->ip() . ':' . (int) floor(time() / $this->windowSeconds);
        $cache = Cache::instance();

        $count = (int) $cache->get($key, 0);
        $count++;

        if ($count > $this->maxRequests) {
            $res->error('RATE_LIMIT', 'Too many requests. Please try again later.', 429);
            return null;
        }

        $cache->set($key, $count, $this->windowSeconds + 10);

        return $next($req, $res);
    }
}

/**
 * CORS Middleware.
 */
class CorsMiddleware
{
    public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID, X-API-Version');
        header('Access-Control-Max-Age: 86400');

        if ($req->method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return $next($req, $res);
    }
}

/**
 * CSRF Middleware.
 */
class CsrfMiddleware
{
    public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        if (in_array($req->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $headerToken = $req->header('X-CSRF-Token', '');
            $bodyToken = $req->body('csrf_token', '');

            if (!$headerToken && !$bodyToken) {
                $res->error('CSRF_ERROR', 'CSRF token is required for state-changing requests.', 403);
                return null;
            }
        }

        return $next($req, $res);
    }
}

/**
 * Audit Middleware — Logs all API requests.
 */
class AuditMiddleware
{
    public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        $start = microtime(true);

        $response = $next($req, $res);

        $duration = round((microtime(true) - $start) * 1000);

        Logger::info('API Request', [
            'method'   => $req->method(),
            'uri'      => $req->uri(),
            'status'   => http_response_code(),
            'duration' => $duration . 'ms',
            'ip'       => $req->ip(),
            'user_id'  => $req->userId(),
        ]);

        return $response;
    }
}
