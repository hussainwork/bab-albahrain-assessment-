<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Gateway\GateEException;

Request::requirePost();
Csrf::guard();

$id = filter_var(Request::input()['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || Container::payments()->find($id) === null) {
    Response::fail(t('error.not_found'), [], 404);
}

try {
    $result = Container::sync()->syncFromGateway($id);
} catch (GateEException $e) {
    Response::fail(t('error.gateway'), [], 502);
} catch (RuntimeException $e) {
    Response::fail(t('error.not_synced'), [], 409);
}

Response::ok(
    Container::details()->build($id, $result['live']),
    $result['changed'] ? t('payments.status_updated') : t('payments.status_unchanged')
);
