<?php

declare(strict_types=1);

namespace App\Gateway;

final class PaymentStatus
{
    public const INITIATED = 'initiated';
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public const ALL = [self::INITIATED, self::PENDING, self::PAID, self::FAILED, self::CANCELLED];

    // A failed or locally cancelled payment may still be paid: the gateway page allows another
    // attempt, and the verified gateway status always wins over our local one.
    private const TRANSITIONS = [
        self::INITIATED => [self::PENDING, self::PAID, self::FAILED, self::CANCELLED],
        self::PENDING => [self::PAID, self::FAILED, self::CANCELLED],
        self::FAILED => [self::PAID],
        self::CANCELLED => [self::PAID],
        self::PAID => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
