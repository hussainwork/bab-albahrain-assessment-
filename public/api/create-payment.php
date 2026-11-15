<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Gateway\GateEException;

Request::requirePost();
Csrf::guard();

$limit = config('app.rate_limit');
if (!RateLimiter::allow('create-payment', $limit['attempts'], $limit['window'])) {
    Response::fail(t('error.rate_limit'), [], 429);
}

try {
    $result = Container::checkout()->start(Request::input(), Lang::current());
} catch (ValidationException $e) {
    Response::fail(t('error.validation'), $e->errors(), 422);
} catch (GateEException $e) {
    Response::fail(t('error.gateway'), [], 502);
}

// Remember which payments this browser started so it may cancel them later.
$_SESSION['my_payments'][] = $result['order_reference'];

Response::ok($result, t('checkout.created'), 201);
