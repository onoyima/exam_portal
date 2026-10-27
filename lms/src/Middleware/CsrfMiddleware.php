<?php
namespace Lms\Middleware;

use Lms\Core\Request;
use Lms\Core\Response;

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
