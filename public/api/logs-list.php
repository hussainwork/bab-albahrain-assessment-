<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Response;

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$direction = (string) ($_GET['direction'] ?? '');
$result = (string) ($_GET['result'] ?? '');

$logs = Container::apiLogs()->search([
    'action' => mb_substr((string) ($_GET['action'] ?? ''), 0, 60),
    'direction' => in_array($direction, ['inbound', 'outbound'], true) ? $direction : '',
    'result' => in_array($result, ['ok', 'error'], true) ? $result : '',
], $page, $perPage);

Response::ok([
    'rows' => $logs['rows'],
    'total' => $logs['total'],
    'page' => $page,
    'pages' => max(1, (int) ceil($logs['total'] / $perPage)),
    'actions' => Container::apiLogs()->actions(),
]);
