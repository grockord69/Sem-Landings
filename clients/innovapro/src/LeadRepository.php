<?php
declare(strict_types=1);
namespace Innova;
final class LeadRepository {
    public function __construct(private \PDO $pdo) {}
    public function create(array $lead, string $requestId, array $attribution): array {
        $hash = hash('sha256', json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $values = $lead + $attribution + ['request_id' => $requestId, 'request_hash' => $hash,
            'consent_rgpd' => 1, 'consent_version' => cfg('app.privacy_version')];
        $columns = array_keys($values);
        try {
            $query = $this->pdo->prepare('INSERT INTO leads (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
            $query->execute(array_values($values)); // Autocommit durable ANTES de cualquier SMTP.
            return ['lead_id' => (int)$this->pdo->lastInsertId(), 'duplicate' => false];
        } catch (\PDOException $error) {
            if ((int)($error->errorInfo[1] ?? 0) !== 1062) { throw $error; }
            $query = $this->pdo->prepare('SELECT id,request_hash FROM leads WHERE request_id=?');
            $query->execute([$requestId]);
            $existing = $query->fetch();
            if (!$existing) { throw $error; }
            if (!hash_equals($existing['request_hash'], $hash)) { throw new \DomainException('idempotency_conflict'); }
            return ['lead_id' => (int)$existing['id'], 'duplicate' => true];
        }
    }
    public function find(int $id): array|false {
        $query = $this->pdo->prepare('SELECT * FROM leads WHERE id=?');
        $query->execute([$id]);
        return $query->fetch();
    }
}
