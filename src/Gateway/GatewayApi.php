<?php

declare(strict_types=1);

namespace App\Gateway;

interface GatewayApi
{
    public function listGateways(): array;

    public function createPayment(array $payment, array $details, ?int $localPaymentId = null): array;

    public function getPayment(string $gatewayPaymentId, string $code, ?int $localPaymentId = null): array;
}
