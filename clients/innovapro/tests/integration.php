<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
use Innova\{LeadRepository,LeadService,Mailer};
use function Innova\{db,attribution};
if (Innova\cfg('db.name') !== 'sem_ci') { throw new RuntimeException('Solo ejecutar sobre sem_ci, BD desechable.'); }
$pdo = db();
$pdo->exec('DROP TABLE IF EXISTS leads');
$pdo->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));
$checks = 0;
function check(bool $value, string $message): void { global $checks; if (!$value) { throw new RuntimeException($message); } $checks++; echo "OK $message\n"; }
$repository = new LeadRepository($pdo);
$lead = ['nombre' => 'Prueba <b>técnica</b>', 'telefono' => '+34600111222', 'email' => 'person@example.test'];
$attr = attribution(['attribution' => ['utm_source' => 'google', 'utm_campaign' => 'CI', 'gclid' => 'test-gclid', 'wbraid' => 'test-wbraid', 'gbraid' => 'test-gbraid']]);
$requestId = bin2hex(random_bytes(16));
$sent = 0;
$service = new LeadService($pdo, function (array $row) use (&$sent, $repository): void {
    check($repository->find((int)$row['id']) !== false, 'lead existe antes de SMTP');
    $sent++;
});
$saved = $service->submit($lead, $requestId, $attr);
$row = $repository->find($saved['lead_id']);
check($row['email_status'] === 'sent' && (int)$row['email_attempts'] === 1 && $row['email_sent_at'] !== null, 'sent con fecha e intento');
check($row['gclid'] === 'test-gclid' && $row['wbraid'] === 'test-wbraid' && $row['gbraid'] === 'test-gbraid' && $row['utm_campaign'] === 'CI', 'lectura de atribución y consentimiento');
$duplicate = $service->submit($lead, $requestId, $attr);
check($duplicate['duplicate'] && $duplicate['lead_id'] === $saved['lead_id'] && $sent === 1, 'idempotencia no repite SMTP');
try { $service->submit(array_replace($lead, ['email' => 'other@example.test']), $requestId, $attr); throw new RuntimeException('conflict absent'); }
catch (DomainException) { check(true, 'clave idempotente con otro contenido da conflicto'); }
$failure = new LeadService($pdo, static function (): void { throw new RuntimeException('credential and PII must never be logged'); });
$failed = $failure->submit($lead, bin2hex(random_bytes(16)), $attr);
$row = $repository->find($failed['lead_id']);
check($row['email_status'] === 'error' && $row['email_last_error'] === 'SMTP_SEND_FAILED' && (int)$row['email_attempts'] === 1, 'fallo SMTP conserva lead y registra código seguro');
$before = (int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
check($service->notify($failed['lead_id'], true), 'reenvío exclusivo de SMTP fallido');
check((int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn() === $before, 'reenvío no crea lead');
check(!$service->notify($failed['lead_id'], true) && !$service->notify($saved['lead_id']), 'no reenvía sent');
$pending = $repository->create($lead, bin2hex(random_bytes(16)), $attr);
check($repository->find($pending['lead_id'])['email_status'] === 'pending', 'pending antes de notificación');
$other = new PDO('mysql:host=127.0.0.1;port=' . Innova\cfg('db.port') . ';dbname=sem_ci', 'sem_ci', 'database-test-only');
$lock = 'sem_mail_' . substr(hash('sha256', 'sem_ci'), 0, 16) . '_' . $pending['lead_id'];
$query = $other->prepare('SELECT GET_LOCK(?,0)'); $query->execute([$lock]);
check(!$service->notify($pending['lead_id']), 'lock impide dos notificaciones concurrentes');
$query = $other->prepare('SELECT RELEASE_LOCK(?)'); $query->execute([$lock]);
check($service->notify($pending['lead_id']), 'lock liberado permite notificar');
foreach (["UPDATE leads SET consent_rgpd=0 WHERE id=" . $saved['lead_id'], "UPDATE leads SET email_status='unknown' WHERE id=" . $saved['lead_id'], "UPDATE leads SET email_sent_at=NULL WHERE id=" . $saved['lead_id']] as $sql) {
    try { $pdo->exec($sql); throw new RuntimeException('constraint absent'); }
    catch (PDOException) { check(true, 'restricción de schema aplicada'); }
}
$smtp = (new LeadService($pdo))->submit($lead, bin2hex(random_bytes(16)), $attr);
check($repository->find($smtp['lead_id'])['email_status'] === 'sent', 'PHPMailer real: STARTTLS + autenticación + dos destinatarios');
$fixture = getenv('TEST_SMTP_DIR');
$files = glob($fixture . '/message-*.json');
check(count($files) >= 1, 'fixture recibió notificación SMTP real');
$message = json_decode(file_get_contents(end($files)), true, 16, JSON_THROW_ON_ERROR);
check($message['recipients'] === ['one@example.test','two@example.test'] && $message['authenticated'] && $message['tls'], 'SMTP destinatarios, auth y TLS verificados');
check(str_contains($message['html'], 'test-gclid') && str_contains($message['html'], '&lt;b&gt;') && str_contains($message['html'], (string)$smtp['lead_id']), 'plantilla incluye atribución, ID y escape');
$recoveryId = bin2hex(random_bytes(16));
$interrupted = $repository->create($lead, $recoveryId, $attr);
$recovered = $service->submit($lead, $recoveryId, $attr);
check($recovered['duplicate'] && $recovered['lead_id'] === $interrupted['lead_id'] && $repository->find($interrupted['lead_id'])['email_status'] === 'sent', 'reintento idempotente recupera pending sin nuevo lead');
$beforeSent = $sent;
$pdo->exec('RENAME TABLE leads TO leads_unavailable');
try {
    try { $service->submit($lead, bin2hex(random_bytes(16)), $attr); throw new RuntimeException('DB failure absent'); }
    catch (PDOException) { check($sent === $beforeSent, 'error INSERT no intenta SMTP ni confirma éxito'); }
} finally { $pdo->exec('RENAME TABLE leads_unavailable TO leads'); }
echo "TOTAL $checks comprobaciones MariaDB/SMTP\n";
