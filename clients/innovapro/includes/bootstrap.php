<?php
declare(strict_types=1);

define('INNOVAPRO_ROOT', dirname(__DIR__));

require_once __DIR__ . '/functions.php';

$configFile = getenv('INNOVAPRO_CONFIG') ?: __DIR__ . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        $configFile = __DIR__ . '/config.example.php';
    } else {
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>Configuración pendiente</title><p>La landing todavía no está configurada.</p>';
        exit;
    }
}

$GLOBALS['innovapro_config'] = require $configFile;
date_default_timezone_set('UTC');
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/leads.php';

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, private, max-age=0');
}

set_exception_handler(static function (Throwable $error): void {
    error_log('[innovapro] unhandled_' . preg_replace('/[^a-zA-Z0-9_]/', '_', get_class($error)));

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }

    if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'form.php')) {
        json_response([
            'ok' => false,
            'code' => 'temporarily_unavailable',
            'message' => 'No se ha podido confirmar el envío. Conserva tus datos y vuelve a intentarlo o llámanos.',
        ], 503);
    }

    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>InnovaPro</title><p>Servicio temporalmente no disponible. Puedes contactar en ' . esc(cfg('contact.phone_display')) . '.</p>';
});
