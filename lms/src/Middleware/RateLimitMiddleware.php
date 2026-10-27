<?php
namespace Lms\Middleware;

use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Cache;

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
