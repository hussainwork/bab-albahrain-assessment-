<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findOrCreate(string $name, string $email, string $mobileCode, string $mobile): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM customers WHERE email = :email AND mobile_code = :code AND mobile = :mobile'
        );
        $stmt->execute(['email' => $email, 'code' => $mobileCode, 'mobile' => $mobile]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            $update = $this->pdo->prepare('UPDATE customers SET name = :name WHERE id = :id');
            $update->execute(['name' => $name, 'id' => $id]);

            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO customers (name, email, mobile_code, mobile) VALUES (:name, :email, :code, :mobile)'
        );
        $insert->execute(['name' => $name, 'email' => $email, 'code' => $mobileCode, 'mobile' => $mobile]);

        return (int) $this->pdo->lastInsertId();
    }
}
