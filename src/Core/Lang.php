<?php

declare(strict_types=1);

namespace App\Core;

final class Lang
{
    private const SUPPORTED = ['en', 'ar'];

    private static string $current = 'en';

    private static array $strings = [];

    public static function boot(): void
    {
        $requested = $_GET['lang'] ?? null;

        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            setcookie('lang', $requested, [
                'expires' => time() + 31536000,
                'path' => '/',
                'samesite' => 'Lax',
            ]);
            $_COOKIE['lang'] = $requested;
        }

        $cookie = $_COOKIE['lang'] ?? 'en';
        self::$current = in_array($cookie, self::SUPPORTED, true) ? $cookie : 'en';
        self::$strings = require dirname(__DIR__, 2) . '/lang/' . self::$current . '.php';
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function dir(): string
    {
        return self::$current === 'ar' ? 'rtl' : 'ltr';
    }

    public static function all(): array
    {
        return self::$strings;
    }

    public static function get(string $key, array $params = []): string
    {
        $text = self::$strings[$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }
}
