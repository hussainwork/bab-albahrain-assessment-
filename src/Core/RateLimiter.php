<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public static function allow(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $hits = array_filter(
            $_SESSION['_rate'][$bucket] ?? [],
            static fn (int $at): bool => $at > $now - $windowSeconds
        );

        if (count($hits) >= $maxAttempts) {
            $_SESSION['_rate'][$bucket] = $hits;

            return false;
        }

        $hits[] = $now;
        $_SESSION['_rate'][$bucket] = array_values($hits);

        return true;
    }
}
