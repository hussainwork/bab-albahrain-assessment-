<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Gateway\GateEException;
use App\Gateway\GatewayApi;
use App\Gateway\PaymentStatus;
use App\Gateway\StatusMapper;
use App\Repositories\PaymentRepository;
use App\Repositories\TransactionRepository;
use PDO;
use RuntimeException;

// Callback, webhook and the Refresh button all sync through this class.
final class PaymentSyncService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly GatewayApi $gateway,
        private readonly PaymentRepository $payments,
        private readonly TransactionRepository $transactions
    ) {
    }

    public function syncFromGateway(int $paymentId): array
    {
        $payment = $this->payments->find($paymentId)
            ?? throw new RuntimeException('Unknown payment ' . $paymentId);

        if (empty($payment['gateway_payment_id'])) {
            throw new RuntimeException('Payment ' . $paymentId . ' has no gateway reference yet');
        }

        $live = $this->gateway->getPayment(
            (string) $payment['gateway_payment_id'],
            (string) $payment['order_reference'],
            $paymentId
        );
        $target = StatusMapper::fromLive($live);

        $changed = false;
        $this->pdo->beginTransaction();
        try {
            // the webhook and the callback can arrive together, so lock the row first
            $locked = $this->payments->lockById($paymentId)
                ?? throw new RuntimeException('Payment vanished: ' . $paymentId);

            foreach (StatusMapper::transactions($live) as $transaction) {
                $this->transactions->upsert(
                    $paymentId,
                    StatusMapper::transactionFields($transaction),
                    $transaction,
                    StatusMapper::outcome($transaction)
                );
            }

            $changed = $this->applyStatus($locked, $target, $live);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['payment' => $this->payments->find($paymentId), 'live' => $live, 'changed' => $changed];
    }

    private function applyStatus(array $locked, string $target, array $live): bool
    {
        $current = (string) $locked['status'];
        if ($current === $target) {
            return false;
        }

        if (!PaymentStatus::canTransition($current, $target)) {
            // pending from the gateway changes nothing for a cancelled or failed order
            if ($target === PaymentStatus::PENDING) {
                return false;
            }

            Logger::warning('Rejected invalid status transition', [
                'payment_id' => $locked['id'],
                'from' => $current,
                'to' => $target,
            ]);

            return false;
        }

        if ($target === PaymentStatus::PAID && !$this->amountMatches($locked, $live)) {
            Logger::error('Gateway reports paid but the amount differs from the order', [
                'payment_id' => $locked['id'],
                'expected' => $locked['amount'],
                'live' => $live['payment']['amount'] ?? null,
            ]);

            return false;
        }

        $this->payments->updateStatus((int) $locked['id'], $target, null);

        return true;
    }

    private function amountMatches(array $locked, array $live): bool
    {
        $liveAmount = $live['payment']['amount'] ?? null;

        return is_numeric($liveAmount) && abs((float) $liveAmount - (float) $locked['amount']) < 0.0005;
    }
}
