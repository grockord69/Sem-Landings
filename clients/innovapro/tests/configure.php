<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || empty($argv[1])) {
    exit(1);
}

$config = require __DIR__ . '/../includes/config.example.php';
$config['app']['url'] = 'http://127.0.0.1:8765';
$config['db'] = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'sem_ci',
    'user' => 'sem_ci',
    'password' => 'database-test-only',
    'ssl_ca' => '',
];
$config['smtp'] = [
    'host' => '127.0.0.1',
    'port' => 1025,
    'encryption' => 'tls',
    'username' => 'sender@example.test',
    'password' => 'smtp-test-only',
    'from_email' => 'sender@example.test',
    'from_name' => 'InnovaPro CI',
    'recipients' => ['one@example.test', 'two@example.test'],
    'timeout_seconds' => 4,
];
$config['legal']['hosting_provider'] = 'CI hosting';
$config['legal']['email_provider'] = 'CI SMTP';
$config['legal']['retention_policy'] = 'Datos sintéticos eliminados al finalizar la ejecución.';

file_put_contents($argv[1], "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n");
