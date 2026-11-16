<?php
/**
 * Guard for maintenance/diagnostic scripts that must never run over HTTP.
 * Include this immediately after the opening PHP tag.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found\n");
}
