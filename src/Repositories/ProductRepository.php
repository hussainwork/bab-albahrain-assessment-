<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(): array
    {
        return $this->pdo
            ->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id')
            ->fetchAll();
    }

    public function findActive(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }
}
