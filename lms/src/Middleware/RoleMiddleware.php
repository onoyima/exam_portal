<?php
namespace Lms\Middleware;

use Lms\Core\Request;
use Lms\Core\Response;

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
