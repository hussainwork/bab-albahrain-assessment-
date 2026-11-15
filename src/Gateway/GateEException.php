<?php

declare(strict_types=1);

namespace App\Gateway;

use RuntimeException;

final class GateEException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $gatewayCode = null,
        private readonly int $httpCode = 0,
        private readonly bool $networkFailure = false
    ) {
        parent::__construct($message);
    }

    public function gatewayCode(): ?string
    {
        return $this->gatewayCode;
    }

    public function httpCode(): int
    {
        return $this->httpCode;
    }

    public function isNetworkFailure(): bool
    {
        return $this->networkFailure;
    }
}
