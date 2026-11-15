<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf';

    public static function token(): string
    {
        $_SESSION[self::SESSION_KEY] ??= bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }

    public static function guard(): void
    {
        $token = Request::header('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);

        if (!self::verify(is_string($token) ? $token : null)) {
            Response::fail(t('error.csrf'), [], 419);
        }
    }
}
