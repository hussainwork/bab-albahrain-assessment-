<?php

declare(strict_types=1);

namespace App\Gateway;

// Turns a payment/get response into a local status (see docs/api-samples for real responses).
final class StatusMapper
{
    private const PAID_WORDS = ['paid', 'success', 'successful', 'approved', 'captured', 'completed', 'complete'];
    private const FAILED_WORDS = ['failed', 'fail', 'declined', 'rejected', 'error', 'denied'];
    private const CANCELLED_WORDS = ['cancelled', 'canceled', 'expired', 'void', 'voided'];

    public static function fromLive(array $live): string
    {
        $paymentStatus = self::word($live['payment']['status'] ?? null);
        $transactions = self::transactions($live);

        foreach ($transactions as $transaction) {
            if (self::outcome($transaction) === 'success') {
                return PaymentStatus::PAID;
            }
        }

        if (in_array($paymentStatus, self::PAID_WORDS, true)) {
            return PaymentStatus::PAID;
        }
        if (in_array($paymentStatus, self::CANCELLED_WORDS, true)) {
            return PaymentStatus::CANCELLED;
        }
        if (in_array($paymentStatus, self::FAILED_WORDS, true)) {
            return PaymentStatus::FAILED;
        }

        $latest = end($transactions);
        if ($latest !== false && self::outcome($latest) === 'failed') {
            return PaymentStatus::FAILED;
        }

        return PaymentStatus::PENDING;
    }

    public static function transactions(array $live): array
    {
        $raw = $live['transaction'] ?? [];
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        // A single transaction may come back as an object instead of a one-item list.
        return array_is_list($raw) ? array_values(array_filter($raw, 'is_array')) : [$raw];
    }

    public static function outcome(array $transaction): string
    {
        $word = self::word(self::pick($transaction, ['status', 'result', 'state']));

        return match (true) {
            in_array($word, self::PAID_WORDS, true), $word === '1' => 'success',
            in_array($word, self::FAILED_WORDS, true), $word === '0' => 'failed',
            default => 'unknown',
        };
    }

    public static function transactionFields(array $transaction): array
    {
        return [
            'id' => self::pick($transaction, ['id', 'transaction_id']),
            'card' => self::maskedCard($transaction['card'] ?? null),
            'auth' => self::pick($transaction, ['authorization_code', 'auth_code']),
            'message' => self::pick($transaction, ['response_message', 'message']),
            'amount' => self::pick($transaction, ['amount', 'paid_amount']),
        ];
    }

    // Gate-E sends the first six and last four digits separately; failed attempts may have neither.
    private static function maskedCard(mixed $card): ?string
    {
        if (!is_array($card)) {
            return null;
        }

        $first = (string) ($card['first_digits'] ?? '');
        $last = (string) ($card['last_digits'] ?? '');
        if ($first === '' && $last === '') {
            return null;
        }

        return $first . str_repeat('*', max(0, 16 - strlen($first) - strlen($last))) . $last;
    }

    private static function word(mixed $value): string
    {
        return is_scalar($value) ? strtolower(trim((string) $value)) : '';
    }

    private static function pick(array $source, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $source;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    continue 2;
                }
                $value = $value[$segment];
            }

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
