<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Bahrain'),
    'vat_rate' => 0.10,
    'max_quantity' => 10,
    'rate_limit' => ['attempts' => 10, 'window' => 300],
    'gateway' => [
        'url' => Env::get('GATEE_API_URL', 'https://api01.gate-e.net/'),
        'user' => Env::get('GATEE_API_USER'),
        'key' => Env::get('GATEE_API_KEY'),
        'gateway_id' => Env::get('GATEE_GATEWAY_ID'),
        'connect_timeout' => 5,
        'timeout' => 20,
    ],
];
