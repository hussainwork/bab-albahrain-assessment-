<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

final class PaymentRepository
{
    private const DETAIL_SELECT = 'SELECT p.*, c.name AS customer_name, c.email AS customer_email,
            c.mobile_code AS customer_mobile_code, c.mobile AS customer_mobile,
            pr.name AS product_name, pr.name_ar AS product_name_ar
        FROM payments p
        JOIN customers c ON c.id = p.customer_id
        JOIN products pr ON pr.id = p.product_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insertInitiated(array $row): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments
                (customer_id, product_id, order_reference, quantity, subtotal, vat_amount, amount, currency, language, status)
             VALUES
                (:customer_id, :product_id, :order_reference, :quantity, :subtotal, :vat_amount, :amount, :currency, :language, \'initiated\')'
        );
        $stmt->execute($row);

        return (int) $this->pdo->lastInsertId();
    }

    public function isReferenceTaken(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? 0) === 1062;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::DETAIL_SELECT . ' WHERE p.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findByReference(string $reference): ?array
    {
        $stmt = $this->pdo->prepare(self::DETAIL_SELECT . ' WHERE p.order_reference = :ref');
        $stmt->execute(['ref' => $reference]);

        return $stmt->fetch() ?: null;
    }

    public function findByGatewayReference(string $gatewayId, string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            self::DETAIL_SELECT . ' WHERE p.gateway_payment_id = :gid AND p.order_reference = :code'
        );
        $stmt->execute(['gid' => $gatewayId, 'code' => $code]);

        return $stmt->fetch() ?: null;
    }

    public function findByGatewayIdOrReference(string $value): ?array
    {
        $stmt = $this->pdo->prepare(
            self::DETAIL_SELECT . ' WHERE p.gateway_payment_id = :gid OR p.order_reference = :ref LIMIT 1'
        );
        $stmt->execute(['gid' => $value, 'ref' => $value]);

        return $stmt->fetch() ?: null;
    }

    public function lockById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function attachGateway(int $id, string $gatewayId, string $code, string $url): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments
                SET gateway_payment_id = :gid, gateway_payment_code = :code, payment_url = :url,
                    status = \'pending\', status_message = NULL
              WHERE id = :id AND status = \'initiated\''
        );
        $stmt->execute(['gid' => $gatewayId, 'code' => $code, 'url' => $url, 'id' => $id]);
    }

    public function updateStatus(int $id, string $status, ?string $message = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments
                SET status = :status,
                    status_message = :message,
                    paid_at = IF(:status_paid = \'paid\', COALESCE(paid_at, NOW()), paid_at)
              WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'status_paid' => $status, 'message' => $message, 'id' => $id]);
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM payments p JOIN customers c ON c.id = p.customer_id ' . $where
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.order_reference, p.amount, p.currency, p.status, p.gateway_payment_id,
                    p.created_at, c.name AS customer_name, c.email AS customer_email, c.mobile AS customer_mobile,
                    pr.name AS product_name, pr.name_ar AS product_name_ar
               FROM payments p
               JOIN customers c ON c.id = p.customer_id
               JOIN products pr ON pr.id = p.product_id ' . $where . '
              ORDER BY p.created_at DESC, p.id DESC
              LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public function stats(): array
    {
        $row = $this->pdo->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = \'paid\'), 0) AS paid,
                    COALESCE(SUM(status = \'failed\'), 0) AS failed,
                    COALESCE(SUM(status IN (\'initiated\', \'pending\')), 0) AS open_count,
                    COALESCE(SUM(CASE WHEN status = \'paid\' THEN amount END), 0) AS revenue
               FROM payments'
        )->fetch();

        $paid = (int) $row['paid'];
        $failed = (int) $row['failed'];

        return [
            'total' => (int) $row['total'],
            'paid' => $paid,
            'failed' => $failed,
            'open' => (int) $row['open_count'],
            'revenue' => number_format((float) $row['revenue'], 3, '.', ''),
            'success_rate' => ($paid + $failed) > 0 ? round($paid / ($paid + $failed) * 100, 1) : 0.0,
        ];
    }

    private function buildFilters(array $filters): array
    {
        $clauses = [];
        $params = [];

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $clauses[] = '(p.order_reference LIKE :q1 OR c.name LIKE :q2 OR c.email LIKE :q3
                           OR c.mobile LIKE :q4 OR p.gateway_payment_id LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $name) {
                $params[$name] = $like;
            }
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $clauses[] = 'p.status = :status';
            $params['status'] = $status;
        }

        if (!empty($filters['from'])) {
            $clauses[] = 'p.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'p.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }
}
