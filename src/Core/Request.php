<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function requirePost(): void
    {
        if (!self::isPost()) {
            header('Allow: POST');
            Response::fail(t('error.method'), [], 405);
        }
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    public static function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode(self::rawBody(), true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function wantsJson(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

        return str_starts_with($path, '/api/')
            || str_starts_with($path, '/webhook/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
