<?php

// ─── Suppress Inline PHP Errors (they must never leak into JSON) ────────────
error_reporting(0);
ini_set('display_errors', '0');
while (ob_get_level() > 0) { ob_end_clean(); }

// ─── Load Portal Config First (for DB constants) ───────────────────────────────
$portalConfig = realpath(__DIR__ . '/../../config.php');
if ($portalConfig && file_exists($portalConfig)) {
    require_once $portalConfig;
}

// ─── Detect Request Type ───────────────────────────────────────────────────────
$isApi = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
$isApi = $isApi || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/exam_portal/lms/public';
$route = str_replace($basePath, '', $uri);
$route = '/' . trim($route, '/');
$isApi = $isApi || (preg_match('#^/(auth|dashboard|courses|modules|lessons|enrollments|assignments|quizzes|discussions|messages|notifications|gradebook|content|analytics|exam-bridge|admin|academic|sync|classes)(/|$)#', $route));

// ─── Serve SPA for Browser Requests ────────────────────────────────────────────
if (!$isApi) {
    $spaFile = __DIR__ . '/index.html';
    if (file_exists($spaFile)) {
        readfile($spaFile);
        exit;
    }
    http_response_code(404);
    echo 'LMS not found';
    exit;
}

// ─── Bootstrap ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../bootstrap.php';

use Lms\Core\Router;
use Lms\Core\Logger;
use Lms\Core\Database;

// ─── CORS Headers ───────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Load Routes ────────────────────────────────────────────────────────────────
try {
    /** @var Router $router */
    $router = require __DIR__ . '/../routes.php';
    $router->dispatch();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => APP_ENV === 'development' ? $e->getMessage() : 'An internal error occurred',
        ],
    ]);
    Logger::error($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
