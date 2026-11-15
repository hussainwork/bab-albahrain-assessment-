<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Response;
use App\Gateway\PaymentStatus;

$status = (string) ($_GET['status'] ?? '');
$date = static fn (string $key): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$key] ?? '')) ? $_GET[$key] : '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 10)));

$result = Container::payments()->search([
    'q' => mb_substr((string) ($_GET['q'] ?? ''), 0, 100),
    'status' => in_array($status, PaymentStatus::ALL, true) ? $status : '',
    'from' => $date('from'),
    'to' => $date('to'),
], $page, $perPage);

Response::ok([
    'rows' => $result['rows'],
    'total' => $result['total'],
    'page' => $page,
    'per_page' => $perPage,
    'pages' => max(1, (int) ceil($result['total'] / $perPage)),
]);
