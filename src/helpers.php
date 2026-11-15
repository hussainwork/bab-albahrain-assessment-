<?php

declare(strict_types=1);

use App\Core\Lang;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function t(string $key, array $params = []): string
{
    return Lang::get($key, $params);
}

function config(string $path, mixed $default = null): mixed
{
    static $loaded = [];

    [$file, $key] = array_pad(explode('.', $path, 2), 2, null);
    $loaded[$file] ??= require dirname(__DIR__) . '/config/' . $file . '.php';

    $value = $loaded[$file];
    if ($key === null) {
        return $value;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function app_url(string $path = ''): string
{
    return config('app.url') . '/' . ltrim($path, '/');
}

function money(string|float|int|null $amount, ?string $currency = 'BHD'): string
{
    $formatted = number_format((float) $amount, 3, '.', ',');

    return $currency === null ? $formatted : t('currency.' . $currency) . ' ' . $formatted;
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return date('d/m/Y H:i:s', strtotime($value));
}
