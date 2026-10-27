<?php
namespace Lms\Middleware;

use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Logger;

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
            $payload = $this->validateToken($token);
            if (!$payload) {
                $res->unauthorized('Invalid or expired token');
                return null;
            }

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
