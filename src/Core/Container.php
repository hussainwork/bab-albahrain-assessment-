<?php

declare(strict_types=1);

namespace App\Core;

use App\Gateway\GateEClient;
use App\Repositories\ApiLogRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\CheckoutService;
use App\Services\PaymentDetailsService;
use App\Services\PaymentSyncService;
use PDO;

final class Container
{
    private static array $shared = [];

    public static function pdo(): PDO
    {
        return Database::connection();
    }

    public static function products(): ProductRepository
    {
        return self::$shared[__FUNCTION__] ??= new ProductRepository(self::pdo());
    }

    public static function customers(): CustomerRepository
    {
        return self::$shared[__FUNCTION__] ??= new CustomerRepository(self::pdo());
    }

    public static function payments(): PaymentRepository
    {
        return self::$shared[__FUNCTION__] ??= new PaymentRepository(self::pdo());
    }

    public static function transactions(): TransactionRepository
    {
        return self::$shared[__FUNCTION__] ??= new TransactionRepository(self::pdo());
    }

    public static function apiLogs(): ApiLogRepository
    {
        return self::$shared[__FUNCTION__] ??= new ApiLogRepository(self::pdo());
    }

    public static function gateway(): GateEClient
    {
        return self::$shared[__FUNCTION__] ??= GateEClient::fromConfig(self::apiLogs());
    }

    public static function checkout(): CheckoutService
    {
        return self::$shared[__FUNCTION__] ??= new CheckoutService(
            self::pdo(),
            self::products(),
            self::customers(),
            self::payments(),
            self::gateway()
        );
    }

    public static function sync(): PaymentSyncService
    {
        return self::$shared[__FUNCTION__] ??= new PaymentSyncService(
            self::pdo(),
            self::gateway(),
            self::payments(),
            self::transactions()
        );
    }

    public static function details(): PaymentDetailsService
    {
        return self::$shared[__FUNCTION__] ??= new PaymentDetailsService(
            self::gateway(),
            self::payments(),
            self::transactions()
        );
    }
}
