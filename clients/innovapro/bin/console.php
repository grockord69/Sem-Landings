<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__ . '/../src/bootstrap.php';
use function Innova\{cfg,db};
$command = $argv[1] ?? '';
if ($command === 'password') {
    fwrite(STDOUT, "Contraseña (entrada estándar, no argumento ni historial; no se oculta): ");
    $password = trim((string)fgets(STDIN));
    if (strlen($password) < 12) { fwrite(STDERR, "Usa al menos 12 caracteres.\n"); exit(1); }
    echo password_hash($password, PASSWORD_DEFAULT) . "\n"; exit;
}
if ($command === 'check') {
    $ok = true;
    foreach (['pdo_mysql', 'openssl', 'iconv', 'session'] as $extension) {
        $present = extension_loaded($extension); echo $extension . ': ' . ($present ? 'OK' : 'FALTA') . "\n"; $ok = $ok && $present;
    }
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) { echo "FALTA composer install\n"; $ok = false; }
    if (!cfg('admin.users')) { echo "FALTA usuario admin\n"; $ok = false; }
    foreach (['smtp.host','smtp.username','smtp.password','smtp.from_email','smtp.recipients','legal.hosting_provider','legal.email_provider','legal.retention_policy'] as $key) {
        if (!cfg($key)) { echo 'FALTA ' . $key . "\n"; $ok = false; }
    }
    try { db()->query('SELECT id FROM leads LIMIT 1'); echo "BD/schema: OK\n"; }
    catch (Throwable) { echo "BD/schema: FALTA o inaccesible\n"; $ok = false; }
    exit($ok ? 0 : 1);
}
if ($command === 'notify-pending') {
    $id = (int)($argv[2] ?? 0);
    $query = db()->prepare("SELECT id FROM leads WHERE id=? AND email_status='pending' AND created_at<DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)");
    $query->execute([$id]);
    if (!$query->fetchColumn()) { fwrite(STDERR, "Solo pending de más de 10 minutos.\n"); exit(1); }
    exit((new Innova\LeadService(db()))->notify($id) ? 0 : 1);
}
echo "Uso: php bin/console.php password | check | notify-pending ID\n"; exit(1);
