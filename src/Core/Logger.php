<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private const SENSITIVE = ['key', 'api_key', 'password', 'pass'];

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function mask(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return '****';
        }

        return substr($secret, 0, 4) . '****' . substr($secret, -4);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $line = sprintf(
            "[%s] %s %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode(self::scrub($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function scrub(array $context): array
    {
        foreach ($context as $name => $value) {
            if (is_array($value)) {
                $context[$name] = self::scrub($value);
            } elseif (in_array(strtolower((string) $name), self::SENSITIVE, true) && is_string($value)) {
                $context[$name] = self::mask($value);
            }
        }

        return $context;
    }
}
