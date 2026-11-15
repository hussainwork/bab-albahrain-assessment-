<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Logger;
use App\Core\Request;
use App\Gateway\GateEException;

header('Content-Type: application/json; charset=utf-8');

$started = microtime(true);
$raw = Request::rawBody();
$body = json_decode($raw, true);
if (!is_array($body)) {
    // accept form-encoded bodies too
    $body = $_POST;
}

$elapsed = static fn (): int => (int) round((microtime(true) - $started) * 1000);

// real deliveries have no code_id, the payment is under data.payment
$identifier = $body['code_id']
    ?? $body['data']['payment']['id']
    ?? $body['data']['payment']['code']
    ?? $body['data']['transaction']['payment_id']
    ?? null;

if (!Request::isPost() || !is_scalar($identifier) || (string) $identifier === '') {
    // log unexpected payloads so they show up on the logs page
    Container::apiLogs()->record(
        'inbound',
        'webhook.rejected',
        ['method' => $_SERVER['REQUEST_METHOD'] ?? '', 'content_type' => $_SERVER['CONTENT_TYPE'] ?? '', 'body' => mb_substr($raw, 0, 2000)],
        ['error' => 'Expected code_id'],
        400,
        $elapsed()
    );
    http_response_code(400);
    echo json_encode(['error' => 'Expected a body with code_id']);
    exit;
}

$codeId = (string) $identifier;
$payment = Container::payments()->findByGatewayIdOrReference($codeId);
$httpCode = 200;
$outcome = ['code_id' => $codeId];

// don't trust the status in the body, sync asks Gate-E for it
if ($payment === null) {
    Logger::warning('Webhook for an unknown payment', ['code_id' => $codeId]);
    $outcome['note'] = 'unknown payment';
} else {
    try {
        $result = Container::sync()->syncFromGateway((int) $payment['id']);
        $outcome['local_status'] = $result['payment']['status'];
    } catch (GateEException | RuntimeException $e) {
        // non-2xx makes Gate-E retry
        $httpCode = 503;
        $outcome['error'] = 'verification failed';
        Logger::error('Webhook verification failed', ['payment_id' => $payment['id'], 'error' => $e->getMessage()]);
    }
}

Container::apiLogs()->record(
    'inbound',
    'webhook.payment',
    $body,
    $outcome,
    $httpCode,
    $elapsed(),
    $payment ? (int) $payment['id'] : null
);

http_response_code($httpCode);
echo json_encode(['code_id' => $codeId]);
