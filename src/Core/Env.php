<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

final class Env
{
    public static function load(string $root): void
    {
        Dotenv::createImmutable($root)->safeLoad();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // A variable set on the real process environment must win over the .env file.
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}
