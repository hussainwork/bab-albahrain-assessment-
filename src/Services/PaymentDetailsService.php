<?php

declare(strict_types=1);

namespace App\Services;

use App\Gateway\GateEException;
use App\Gateway\GatewayApi;
use App\Gateway\StatusMapper;
use App\Repositories\PaymentRepository;
use App\Repositories\TransactionRepository;

final class PaymentDetailsService
{
    public function __construct(
        private readonly GatewayApi $gateway,
        private readonly PaymentRepository $payments,
        private readonly TransactionRepository $transactions
    ) {
    }

    public function build(int $paymentId, ?array $live = null): ?array
    {
        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            return null;
        }

        $liveError = null;
        if ($live === null && !empty($payment['gateway_payment_id'])) {
            try {
                $live = $this->gateway->getPayment(
                    (string) $payment['gateway_payment_id'],
                    (string) $payment['order_reference'],
                    $paymentId
                );
            } catch (GateEException $e) {
                $liveError = $e->getMessage();
            }
        }

        $transactions = $this->decodeTransactions($this->transactions->forPayment($paymentId));

        return [
            'payment' => $this->publicPayment($payment),
            'transactions' => $transactions,
            'live' => $live,
            'live_error' => $liveError,
            'comparison' => $live === null ? [] : $this->compare($payment, $transactions, $live),
            'timeline' => $this->timeline($payment, $transactions),
        ];
    }

    private function publicPayment(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'order_reference' => $p['order_reference'],
            'status' => $p['status'],
            'status_message' => $p['status_message'],
            'amount' => $p['amount'],
            'subtotal' => $p['subtotal'],
            'vat_amount' => $p['vat_amount'],
            'currency' => $p['currency'],
            'quantity' => (int) $p['quantity'],
            'language' => $p['language'],
            'gateway_payment_id' => $p['gateway_payment_id'],
            'gateway_payment_code' => $p['gateway_payment_code'],
            'payment_url' => $p['payment_url'],
            'product_name' => $p['product_name'],
            'product_name_ar' => $p['product_name_ar'],
            'customer_name' => $p['customer_name'],
            'customer_email' => $p['customer_email'],
            'customer_mobile' => '+' . $p['customer_mobile_code'] . ' ' . $p['customer_mobile'],
            'paid_at' => $p['paid_at'],
            'created_at' => $p['created_at'],
            'updated_at' => $p['updated_at'],
        ];
    }

    private function decodeTransactions(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['raw_response'] = json_decode((string) $row['raw_response'], true);
        }

        return $rows;
    }

    private function compare(array $p, array $transactions, array $live): array
    {
        $lp = $live['payment'] ?? [];
        $client = $lp['client'] ?? [];

        $rows = [
            ['status', $p['status'], StatusMapper::fromLive($live)],
            ['order_reference', $p['order_reference'], $lp['code'] ?? null],
            ['gateway_payment_id', $p['gateway_payment_id'], $lp['id'] ?? null],
            ['amount', $p['amount'], $lp['amount'] ?? null],
            ['currency', $p['currency'], $lp['currency'] ?? null],
            ['amount_paid', $p['status'] === 'paid' ? $p['amount'] : '0.000', $lp['amount_paid'] ?? null],
            ['customer_name', $p['customer_name'], $client['name'] ?? null],
            ['customer_email', $p['customer_email'], $client['email'] ?? null],
            ['customer_mobile', '+' . $p['customer_mobile_code'] . $p['customer_mobile'], '+' . ($client['mobile_code'] ?? '') . ($client['mobile'] ?? '')],
            ['transactions', (string) count($transactions), (string) count(StatusMapper::transactions($live))],
        ];

        return array_map(fn (array $r): array => [
            'field' => $r[0],
            'db' => $r[1] === null ? null : (string) $r[1],
            'live' => $r[2] === null ? null : (string) $r[2],
            'match' => $this->same($r[1], $r[2]),
        ], $rows);
    }

    private function same(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.0005;
        }

        return strtolower(trim((string) $a)) === strtolower(trim((string) $b));
    }

    private function timeline(array $p, array $transactions): array
    {
        $events = [['type' => 'created', 'at' => $p['created_at'], 'detail' => null]];

        foreach ($transactions as $t) {
            $events[] = ['type' => 'transaction', 'at' => $t['created_at'], 'detail' => $t['status'] . ($t['response_message'] ? ': ' . $t['response_message'] : '')];
        }
        if ($p['paid_at'] !== null) {
            $events[] = ['type' => 'paid', 'at' => $p['paid_at'], 'detail' => null];
        } elseif (in_array($p['status'], ['failed', 'cancelled'], true)) {
            $events[] = ['type' => $p['status'], 'at' => $p['updated_at'], 'detail' => $p['status_message']];
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) $a['at'], (string) $b['at']));

        return $events;
    }
}
