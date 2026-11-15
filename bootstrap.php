<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Lang;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__);
date_default_timezone_set((string) config('app.timezone'));

ini_set('display_errors', '0');
error_reporting(E_ALL);

$behindTls = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $behindTls,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

Lang::boot();
sendSecurityHeaders();

set_exception_handler(static function (Throwable $e): void {
    Logger::error('Unhandled exception', [
        'type' => $e::class,
        'message' => $e->getMessage(),
        'at' => $e->getFile() . ':' . $e->getLine(),
    ]);

    if (Request::wantsJson()) {
        Response::fail(t('error.server'), [], 500);
    }

    http_response_code(500);
    require __DIR__ . '/views/500.php';
    exit;
});

function sendSecurityHeaders(): void
{
    $appOrigin = (string) config('app.url');
    $framers = array_unique(["'self'", $appOrigin, 'http://localhost:8000', 'http://127.0.0.1:8000']);

    // The gateway redirects back inside the checkout iframe, so frame-src also needs 'self'.
    $csp = [
        "default-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com",
        "img-src 'self' data: https://file.gate-e.net",
        "frame-src 'self' https://*.gate-e.net",
        "connect-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        'frame-ancestors ' . implode(' ', $framers),
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
