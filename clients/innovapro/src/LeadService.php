<?php
declare(strict_types=1);
namespace Innova;
final class LeadService {
    private LeadRepository $repository;
    private \Closure $send;
    public function __construct(private \PDO $pdo, ?callable $send = null) {
        $this->repository = new LeadRepository($pdo);
        // Inyección de dependencia para pruebas; no flag ni modo de aplicación.
        $this->send = $send ? \Closure::fromCallable($send) : (new Mailer())->send(...);
    }
    public function submit(array $lead, string $requestId, array $attribution): array {
        $saved = $this->repository->create($lead, $requestId, $attribution);
        // notify solo admite pending; un reintento puede recuperar una interrupción previa.
        // sent/error no se reenvían aquí. El error SMTP se reintenta exclusivamente en admin.
        try { $this->notify($saved['lead_id']); }
        catch (\Throwable) { safe_log('mail_status_update_failed'); } // Lead ya confirmado.
        return $saved;
    }
    public function notify(int $id, bool $retry = false): bool {
        // Lock de conexión, liberado incluso si PHP termina. No enviar dos correos concurrentes.
        $lock = 'sem_mail_' . substr(hash('sha256', (string)cfg('db.name')), 0, 16) . '_' . $id;
        $query = $this->pdo->prepare('SELECT GET_LOCK(?,0)'); $query->execute([$lock]);
        if ((int)$query->fetchColumn() !== 1) { return false; }
        try {
            $lead = $this->repository->find($id);
            if (!$lead || $lead['email_status'] !== ($retry ? 'error' : 'pending')) { return false; }
            $this->pdo->prepare('UPDATE leads SET email_attempts=email_attempts+1 WHERE id=?')->execute([$id]);
            try { ($this->send)($lead); }
            catch (\Throwable $error) {
                $code = $error->getMessage() === 'SMTP_NOT_CONFIGURED' ? 'SMTP_NOT_CONFIGURED' : 'SMTP_SEND_FAILED';
                $this->pdo->prepare("UPDATE leads SET email_status='error',email_last_error=?,email_sent_at=NULL WHERE id=?")->execute([$code, $id]);
                safe_log($code);
                return false;
            }
            $this->pdo->prepare("UPDATE leads SET email_status='sent',email_last_error=NULL,email_sent_at=UTC_TIMESTAMP() WHERE id=?")->execute([$id]);
            return true;
        } finally { $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]); }
    }
}
