<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$ok = true;
foreach (['pdo_mysql', 'openssl', 'iconv'] as $extension) {
    $present = extension_loaded($extension);
    echo $extension . ': ' . ($present ? 'OK' : 'FALTA') . PHP_EOL;
    $ok = $ok && $present;
}

foreach (['db.name', 'db.user', 'smtp.host', 'smtp.username', 'smtp.password', 'smtp.from_email', 'smtp.recipients', 'legal.retention_policy'] as $key) {
    if (!cfg($key)) {
        echo 'FALTA ' . $key . PHP_EOL;
        $ok = false;
    }
}

if (!is_readable(__DIR__ . '/.htpasswd')) {
    echo "FALTA includes/.htpasswd (panel privado)\n";
    $ok = false;
}

try {
    db()->query('SELECT id FROM leads LIMIT 1');
    echo "BD/schema: OK\n";
} catch (Throwable) {
    echo "BD/schema: FALTA o inaccesible\n";
    $ok = false;
}

exit($ok ? 0 : 1);
