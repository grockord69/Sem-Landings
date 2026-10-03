<?php
/** Genera configuración privada de la suite; no altera el comportamiento de la aplicación. */
declare(strict_types=1);
$path = $argv[1] ?? '';
if (!$path) { exit(1); }
$config = require __DIR__ . '/../config/config.example.php';
$config['app']['url'] = 'http://127.0.0.1:8765';
$config['db'] = ['host' => '127.0.0.1', 'port' => (int)(getenv('TEST_DB_PORT') ?: 3306), 'name' => 'sem_ci', 'user' => 'sem_ci', 'password' => 'database-test-only', 'ssl_ca' => ''];
$config['smtp'] = ['host' => '127.0.0.1', 'port' => 1025, 'encryption' => 'tls', 'username' => 'sender@example.test', 'password' => 'smtp-test-only', 'from_email' => 'sender@example.test', 'from_name' => 'InnovaPro', 'recipients' => ['one@example.test', 'two@example.test'], 'timeout_seconds' => 2];
$config['admin']['users'] = ['qa' => password_hash('admin-test-only', PASSWORD_DEFAULT)];
file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
if (PHP_OS_FAMILY !== 'Windows') { chmod($path, 0600); }
echo "Private test configuration created.\n";
