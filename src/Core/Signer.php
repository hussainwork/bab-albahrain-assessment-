<?php

declare(strict_types=1);

namespace App\Core;

final class Signer
{
    public static function sign(string $value): string
    {
        return substr(hash_hmac('sha256', $value, (string) config('app.gateway.key')), 0, 20);
    }

    public static function check(string $value, string $signature): bool
    {
        return hash_equals(self::sign($value), $signature);
    }
}
