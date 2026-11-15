<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Core\Logger;
use App\Repositories\ApiLogRepository;

final class GateEClient implements GatewayApi
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $user,
        private readonly string $key,
        private readonly ApiLogRepository $logs,
        private readonly int $connectTimeout = 5,
        private readonly int $timeout = 20
    ) {
    }

    public static function fromConfig(ApiLogRepository $logs): self
    {
        $cfg = config('app.gateway');

        return new self(
            (string) $cfg['url'],
            (string) $cfg['user'],
            (string) $cfg['key'],
            $logs,
            (int) $cfg['connect_timeout'],
            (int) $cfg['timeout']
        );
    }

    public function listGateways(): array
    {
        $response = $this->send('gateway', 'list', []);

        return $response['data']['gateway'] ?? [];
    }

    public function createPayment(array $payment, array $details, ?int $localPaymentId = null): array
    {
        $response = $this->send(
            'payment',
            'create',
            ['payment' => $payment, 'payment_details' => $details],
            $localPaymentId
        );

        $created = $response['data']['payment'] ?? [];
        if (empty($created['id']) || empty($created['link'])) {
            throw new GateEException('Gate-E did not return a payment id and link');
        }

        return $created;
    }

    public function getPayment(string $gatewayPaymentId, string $code, ?int $localPaymentId = null): array
    {
        $response = $this->send(
            'payment',
            'get',
            ['payment' => ['id' => $gatewayPaymentId, 'code' => $code]],
            $localPaymentId
        );

        return $response['data'] ?? [];
    }

    private function send(string $resource, string $action, array $body, ?int $localPaymentId = null): array
    {
        $payload = ['api' => [
            'version' => '01',
            'user' => $this->user,
            'key' => $this->key,
            'resource' => $resource,
            'action' => $action,
            'response' => ['type' => 'json'],
        ]] + $body;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $label = $resource . '.' . $action;

        $started = microtime(true);
        $attempt = 0;
        do {
            $attempt++;
            [$raw, $httpCode, $errno, $error] = $this->post($json);
        } while ($errno !== 0 && $attempt < 2);
        $elapsed = (int) round((microtime(true) - $started) * 1000);

        $maskedRequest = $this->maskRequest($payload);

        if ($errno !== 0) {
            $this->logs->record('outbound', $label, $maskedRequest, ['error' => $error, 'attempts' => $attempt], 0, $elapsed, $localPaymentId);
            Logger::error('Gate-E network failure', ['action' => $label, 'error' => $error, 'attempts' => $attempt]);

            throw new GateEException('Could not reach Gate-E: ' . $error, null, 0, true);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $this->logs->record('outbound', $label, $maskedRequest, ['raw' => mb_substr((string) $raw, 0, 2000)], $httpCode, $elapsed, $localPaymentId);

            throw new GateEException('Gate-E returned an unreadable response', null, $httpCode);
        }

        $this->logs->record('outbound', $label, $maskedRequest, $decoded, $httpCode, $elapsed, $localPaymentId);

        if ($httpCode >= 500 || (int) ($decoded['status'] ?? 0) !== 1) {
            $message = (string) ($decoded['message'] ?? '') ?: 'Gate-E rejected the request';
            Logger::warning('Gate-E business error', [
                'action' => $label,
                'code' => $decoded['status_code'] ?? null,
                'message' => $message,
            ]);

            throw new GateEException($message, isset($decoded['status_code']) ? (string) $decoded['status_code'] : null, $httpCode);
        }

        return $decoded;
    }

    private function post(string $json): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $result = [$raw, (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), curl_errno($ch), curl_error($ch)];
        curl_close($ch);

        return $result;
    }

    private function maskRequest(array $payload): array
    {
        if (isset($payload['api']['key'])) {
            $payload['api']['key'] = Logger::mask((string) $payload['api']['key']);
        }

        return $payload;
    }
}
