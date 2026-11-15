<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Signer;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Gateway\GateEException;
use App\Gateway\GatewayApi;
use App\Gateway\PaymentStatus;
use App\Repositories\CustomerRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use PDO;
use PDOException;

final class CheckoutService
{
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductRepository $products,
        private readonly CustomerRepository $customers,
        private readonly PaymentRepository $payments,
        private readonly GatewayApi $gateway
    ) {
    }

    public function start(array $input, string $language): array
    {
        $validator = (new Validator($input))
            ->required('name')->minLength('name', 2)->maxLength('name', 120)
            ->required('email')->email('email')->maxLength('email', 190)
            ->required('mobile')->bahrainMobile('mobile')
            ->integerBetween('product_id', 1, PHP_INT_MAX)
            ->integerBetween('quantity', 1, (int) config('app.max_quantity'));

        $errors = $validator->errors();
        $product = null;
        if (!isset($errors['product_id'])) {
            $product = $this->products->findActive((int) $validator->value('product_id'));
            if ($product === null) {
                $errors['product_id'] = t('validation.product');
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // price always comes from the DB
        $quantity = (int) $validator->value('quantity');
        $unit = $this->toFils((string) $product['price']);
        $subtotal = $unit * $quantity;
        $vat = (int) round($subtotal * (float) config('app.vat_rate'));
        $total = $subtotal + $vat;

        $paymentId = null;
        $reference = null;
        $this->pdo->beginTransaction();
        try {
            $customerId = $this->customers->findOrCreate(
                $validator->value('name'),
                strtolower($validator->value('email')),
                '973',
                Validator::normaliseMobile($validator->value('mobile'))
            );

            [$paymentId, $reference] = $this->insertPayment([
                'customer_id' => $customerId,
                'product_id' => (int) $product['id'],
                'quantity' => $quantity,
                'subtotal' => $this->fromFils($subtotal),
                'vat_amount' => $this->fromFils($vat),
                'amount' => $this->fromFils($total),
                'currency' => $product['currency'],
                'language' => $language,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $mobile = Validator::normaliseMobile($validator->value('mobile'));

        try {
            $created = $this->gateway->createPayment(
                $this->gatewayPayment($reference, $product, $total, $language, $validator, $mobile),
                $this->gatewayLines($product, $quantity, $unit, $subtotal, $vat),
                $paymentId
            );
        } catch (GateEException $e) {
            $this->payments->updateStatus($paymentId, PaymentStatus::FAILED, mb_substr($e->getMessage(), 0, 255));
            Logger::error('Payment creation failed at the gateway', ['payment_id' => $paymentId, 'reference' => $reference]);

            throw $e;
        }

        $this->payments->attachGateway($paymentId, (string) $created['id'], $reference, (string) $created['link']);

        return [
            'payment_id' => $paymentId,
            'order_reference' => $reference,
            'payment_url' => (string) $created['link'],
            'amount' => $this->fromFils($total),
            'currency' => (string) $product['currency'],
        ];
    }

    public function cancel(string $reference): bool
    {
        $payment = $this->payments->findByReference($reference);
        if ($payment === null || !PaymentStatus::canTransition($payment['status'], PaymentStatus::CANCELLED)) {
            return false;
        }

        $this->payments->updateStatus((int) $payment['id'], PaymentStatus::CANCELLED, 'Cancelled by the customer');

        return true;
    }

    public function retryLink(string $reference): string
    {
        return '/?retry=' . rawurlencode($reference) . '&sig=' . Signer::sign($reference);
    }

    private function insertPayment(array $row): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = $this->newReference();
            try {
                return [$this->payments->insertInitiated($row + ['order_reference' => $reference]), $reference];
            } catch (PDOException $e) {
                if (!$this->payments->isReferenceTaken($e)) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not generate a unique order reference');
    }

    private function newReference(): string
    {
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
        }

        return 'BAB-' . date('Ymd') . '-' . $suffix;
    }

    private function gatewayPayment(
        string $reference,
        array $product,
        int $totalFils,
        string $language,
        Validator $validator,
        string $mobile
    ): array {
        return [
            'type' => 'pay',
            'code' => $reference,
            'amount' => $this->fromFils($totalFils),
            'currency' => $product['currency'],
            'description' => $product['name'],
            'language' => $language,
            'referance' => $reference,
            'gateway' => ['id' => (string) config('app.gateway.gateway_id')],
            'client' => [
                'name' => $validator->value('name'),
                'email' => strtolower($validator->value('email')),
                'mobile_code' => '973',
                'mobile' => $mobile,
                'country' => 'BH',
            ],
            'settings' => [
                'callback_url' => app_url('webhook/gate-e.php'),
                'redirect_url' => app_url('callback.php'),
                'create_code' => 1,
                'calculate_payment' => 0,
            ],
        ];
    }

    private function gatewayLines(array $product, int $quantity, int $unit, int $subtotal, int $vat): array
    {
        $lines = [[
            'name' => $product['name'],
            'quantity' => $quantity,
            'price' => $this->fromFils($unit),
            'amount' => $this->fromFils($subtotal),
        ]];

        if ($vat > 0) {
            $lines[] = [
                'name' => 'VAT ' . rtrim(rtrim(number_format((float) config('app.vat_rate') * 100, 2), '0'), '.') . '%',
                'quantity' => 1,
                'price' => $this->fromFils($vat),
                'amount' => $this->fromFils($vat),
            ];
        }

        return $lines;
    }

    private function toFils(string $amount): int
    {
        return (int) round((float) $amount * 1000);
    }

    private function fromFils(int $fils): string
    {
        return sprintf('%d.%03d', intdiv($fils, 1000), $fils % 1000);
    }
}
