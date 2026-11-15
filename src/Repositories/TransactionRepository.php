<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TransactionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    // Returns true if a new row was inserted, false if it already existed.
    public function upsert(int $paymentId, array $fields, array $raw, string $outcome): bool
    {
        $fingerprint = $fields['id'] !== null
            ? sha1($fields['id'])
            : sha1(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions
                (payment_id, fingerprint, gateway_transaction_id, status, amount, card_masked, auth_code, response_message, raw_response)
             VALUES
                (:payment_id, :fingerprint, :txn_id, :status, :amount, :card, :auth, :message, :raw)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                amount = VALUES(amount),
                card_masked = VALUES(card_masked),
                auth_code = VALUES(auth_code),
                response_message = VALUES(response_message),
                raw_response = VALUES(raw_response)'
        );
        $stmt->execute([
            'payment_id' => $paymentId,
            'fingerprint' => $fingerprint,
            'txn_id' => $fields['id'],
            'status' => $outcome,
            'amount' => is_numeric($fields['amount']) ? $fields['amount'] : null,
            'card' => $fields['card'] !== null ? mb_substr($fields['card'], 0, 30) : null,
            'auth' => $fields['auth'] !== null ? mb_substr($fields['auth'], 0, 30) : null,
            'message' => $fields['message'] !== null ? mb_substr($fields['message'], 0, 255) : null,
            'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        // 1 = inserted, 2 = duplicate key updated
        return $stmt->rowCount() === 1;
    }

    public function forPayment(int $paymentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, gateway_transaction_id, status, amount, card_masked, auth_code, response_message, raw_response, created_at
               FROM transactions WHERE payment_id = :id ORDER BY id'
        );
        $stmt->execute(['id' => $paymentId]);

        return $stmt->fetchAll();
    }

    public function latestForPayment(int $paymentId): ?array
    {
        $rows = $this->forPayment($paymentId);

        return $rows === [] ? null : end($rows);
    }
}
