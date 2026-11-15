<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function ok(mixed $data = null, ?string $message = null, int $status = 200): never
    {
        self::send(true, $data, $message, [], $status);
    }

    public static function fail(string $message, array $errors = [], int $status = 400): never
    {
        self::send(false, null, $message, $errors, $status);
    }

    private static function send(bool $success, mixed $data, ?string $message, array $errors, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'errors' => (object) $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        exit;
    }
}
