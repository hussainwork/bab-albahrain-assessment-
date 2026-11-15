<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use Throwable;

final class ApiLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        string $direction,
        string $action,
        array|string|null $request,
        array|string|null $response,
        int $httpCode,
        int $durationMs,
        ?int $paymentId = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_logs (payment_id, direction, action, request_body, response_body, http_code, duration_ms)
                 VALUES (:payment_id, :direction, :action, :request, :response, :http, :ms)'
            );
            $stmt->execute([
                'payment_id' => $paymentId,
                'direction' => $direction,
                'action' => $action,
                'request' => $this->encode($request),
                'response' => $this->encode($response),
                'http' => $httpCode,
                'ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            // logging must not break a payment
            Logger::error('Could not write api_logs row', ['error' => $e->getMessage()]);
        }
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['action'])) {
            $clauses[] = 'action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['direction'])) {
            $clauses[] = 'direction = :direction';
            $params['direction'] = $filters['direction'];
        }
        if (($filters['result'] ?? '') === 'ok') {
            $clauses[] = 'http_code BETWEEN 200 AND 299';
        } elseif (($filters['result'] ?? '') === 'error') {
            $clauses[] = '(http_code < 200 OR http_code > 299)';
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM api_logs ' . $where);
        $count->execute($params);

        $stmt = $this->pdo->prepare(
            'SELECT id, payment_id, direction, action, request_body, response_body, http_code, duration_ms, created_at
               FROM api_logs ' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function actions(): array
    {
        return $this->pdo->query('SELECT DISTINCT action FROM api_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    }

    private function encode(array|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = ['raw' => mb_substr($value, 0, 4000)];
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
