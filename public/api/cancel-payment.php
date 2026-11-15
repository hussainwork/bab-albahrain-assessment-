<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

Request::requirePost();
Csrf::guard();

$reference = (string) (Request::input()['order_reference'] ?? '');
if (!in_array($reference, $_SESSION['my_payments'] ?? [], true)) {
    Response::fail(t('error.not_found'), [], 404);
}

if (!Container::checkout()->cancel($reference)) {
    Response::fail(t('error.cannot_cancel'), [], 409);
}

Response::ok(['order_reference' => $reference], t('checkout.cancelled'));
