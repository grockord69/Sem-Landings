<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (cfg('db.name') !== 'sem_ci') {
    throw new RuntimeException('Solo ejecutar sobre sem_ci.');
}

$pdo = db();
$pdo->exec('DROP TABLE IF EXISTS leads');
$pdo->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));

$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
    $checks++;
    echo "OK $label\n";
}

$lead = [
    'nombre' => 'Prueba <b>técnica</b>',
    'telefono' => '+34600111222',
    'email' => 'person@example.test',
];
$attribution = attribution_data(['attribution' => [
    'utm_source' => 'google',
    'utm_campaign' => 'CI',
    'gclid' => 'test-gclid',
    'wbraid' => 'test-wbraid',
    'gbraid' => 'test-gbraid',
]]);

$fixture = getenv('TEST_SMTP_DIR');
if (!$fixture) {
    throw new RuntimeException('TEST_SMTP_DIR ausente.');
}

$requestId = bin2hex(random_bytes(16));
$saved = submit_lead($lead, $requestId, $attribution);
$row = find_lead($saved['lead_id']);
check($row !== false, 'lead guardado');
check($row['email_status'] === 'sent' && (int) $row['email_attempts'] === 1 && $row['email_sent_at'] !== null, 'SMTP enviado después del INSERT');
check($row['gclid'] === 'test-gclid' && $row['utm_campaign'] === 'CI', 'atribución almacenada');

$filesBeforeDuplicate = glob($fixture . '/message-*.json') ?: [];
$duplicate = submit_lead($lead, $requestId, $attribution);
$filesAfterDuplicate = glob($fixture . '/message-*.json') ?: [];
check($duplicate['duplicate'] && $duplicate['lead_id'] === $saved['lead_id'], 'idempotencia devuelve mismo lead');
check(count($filesAfterDuplicate) === count($filesBeforeDuplicate), 'idempotencia no repite SMTP enviado');

try {
    submit_lead(array_replace($lead, ['email' => 'other@example.test']), $requestId, $attribution);
    throw new RuntimeException('Faltó conflicto idempotente.');
} catch (DomainException) {
    check(true, 'mismo request_id con otros datos da conflicto');
}

file_put_contents($fixture . '/fail', '1');
$failed = submit_lead($lead, bin2hex(random_bytes(16)), $attribution);
unlink($fixture . '/fail');
$failedRow = find_lead($failed['lead_id']);
check($failedRow['email_status'] === 'error' && $failedRow['email_last_error'] === 'SMTP_SEND_FAILED', 'fallo SMTP conserva lead y marca error');
check((int) $failedRow['email_attempts'] === 1, 'fallo SMTP registra intento');

$pendingRequest = bin2hex(random_bytes(16));
$pending = store_lead($lead, $pendingRequest, $attribution);
check(find_lead($pending['lead_id'])['email_status'] === 'pending', 'INSERT aislado queda pending');
$recovered = submit_lead($lead, $pendingRequest, $attribution);
check($recovered['duplicate'] && find_lead($pending['lead_id'])['email_status'] === 'sent', 'reintento idéntico recupera pending sin duplicar');

foreach ([
    "UPDATE leads SET consent_rgpd=0 WHERE id=" . $saved['lead_id'],
    "UPDATE leads SET email_status='unknown' WHERE id=" . $saved['lead_id'],
    "UPDATE leads SET email_sent_at=NULL WHERE id=" . $saved['lead_id'],
] as $sql) {
    try {
        $pdo->exec($sql);
        throw new RuntimeException('Faltó restricción de schema.');
    } catch (PDOException) {
        check(true, 'restricción de schema aplicada');
    }
}

$messages = glob($fixture . '/message-*.json') ?: [];
check(count($messages) >= 2, 'fixture recibió emails reales');
$message = json_decode(file_get_contents(end($messages)), true, 16, JSON_THROW_ON_ERROR);
check($message['recipients'] === ['one@example.test', 'two@example.test'], 'múltiples destinatarios SMTP');
check($message['authenticated'] && $message['tls'], 'SMTP autenticado con STARTTLS');
check(str_contains($message['html'], '&lt;b&gt;') && str_contains($message['html'], 'test-gclid'), 'plantilla escapa HTML e incluye atribución');

$beforeFailure = count(glob($fixture . '/message-*.json') ?: []);
$pdo->exec('RENAME TABLE leads TO leads_unavailable');
try {
    try {
        submit_lead($lead, bin2hex(random_bytes(16)), $attribution);
        throw new RuntimeException('Faltó error de BD.');
    } catch (PDOException) {
        check(count(glob($fixture . '/message-*.json') ?: []) === $beforeFailure, 'error de BD no intenta SMTP');
    }
} finally {
    $pdo->exec('RENAME TABLE leads_unavailable TO leads');
}

echo "TOTAL $checks comprobaciones MariaDB/SMTP\n";
