<?php
namespace Lms\Middleware;

use Lms\Core\Request;
use Lms\Core\Response;

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
