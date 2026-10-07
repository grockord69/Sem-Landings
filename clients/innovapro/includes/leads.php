<?php
declare(strict_types=1);

function find_lead(int $id): array|false
{
    $query = db()->prepare('SELECT * FROM leads WHERE id = ?');
    $query->execute([$id]);
    return $query->fetch();
}

function store_lead(array $lead, string $requestId, array $attribution): array
{
    $requestHash = hash('sha256', json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    $data = $lead + $attribution + [
        'request_id' => $requestId,
        'request_hash' => $requestHash,
        'consent_rgpd' => 1,
        'consent_version' => cfg('app.privacy_version'),
    ];

    $columns = array_keys($data);
    $sql = 'INSERT INTO leads (' . implode(',', $columns) . ') VALUES ('
        . implode(',', array_fill(0, count($columns), '?')) . ')';

    try {
        $query = db()->prepare($sql);
        $query->execute(array_values($data));
        return ['lead_id' => (int) db()->lastInsertId(), 'duplicate' => false];
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== 1062) {
            throw $error;
        }

        $query = db()->prepare('SELECT id, request_hash FROM leads WHERE request_id = ?');
        $query->execute([$requestId]);
        $existing = $query->fetch();
        if (!$existing) {
            throw $error;
        }
        if (!hash_equals((string) $existing['request_hash'], $requestHash)) {
            throw new DomainException('idempotency_conflict');
        }

        return ['lead_id' => (int) $existing['id'], 'duplicate' => true];
    }
}

function notify_lead(int $id): bool
{
    $lockName = 'innovapro_mail_' . $id;
    $lock = db()->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        return false;
    }

    try {
        $lead = find_lead($id);
        if (!$lead || $lead['email_status'] !== 'pending') {
            return false;
        }

        db()->prepare('UPDATE leads SET email_attempts = email_attempts + 1 WHERE id = ?')->execute([$id]);

        try {
            send_lead_email($lead);
            db()->prepare("UPDATE leads SET email_status='sent', email_last_error=NULL, email_sent_at=UTC_TIMESTAMP() WHERE id=?")
                ->execute([$id]);
            return true;
        } catch (Throwable $error) {
            $code = $error->getMessage() === 'SMTP_NOT_CONFIGURED' ? 'SMTP_NOT_CONFIGURED' : 'SMTP_SEND_FAILED';
            db()->prepare("UPDATE leads SET email_status='error', email_last_error=?, email_sent_at=NULL WHERE id=?")
                ->execute([$code, $id]);
            error_log('[innovapro] ' . $code);
            return false;
        }
    } finally {
        db()->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}

function submit_lead(array $lead, string $requestId, array $attribution): array
{
    $saved = store_lead($lead, $requestId, $attribution);

    // Si el primer intento se interrumpió tras el INSERT, un reintento idéntico
    // recupera la notificación pendiente sin crear otro lead.
    $row = find_lead($saved['lead_id']);
    if ($row && $row['email_status'] === 'pending') {
        notify_lead($saved['lead_id']);
    }

    return $saved;
}
