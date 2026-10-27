<?php
namespace Lms\Middleware;

use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Logger;

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
