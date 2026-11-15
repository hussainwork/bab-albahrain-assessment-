<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $cfg = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'],
            $cfg['name']
        );

        self::$connection = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Keep NOW() and TIMESTAMP columns in Bahrain time so the data reads naturally in the DB tool.
        self::$connection->exec("SET time_zone = '+03:00'");

        return self::$connection;
    }
}
