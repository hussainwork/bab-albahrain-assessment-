<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Core\Container;
use App\Core\Response;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    Response::fail(t('error.not_found'), [], 404);
}

$details = Container::details()->build($id);
if ($details === null) {
    Response::fail(t('error.not_found'), [], 404);
}

Response::ok($details);
