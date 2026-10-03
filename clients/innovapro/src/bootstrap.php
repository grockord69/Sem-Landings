<?php
declare(strict_types=1);
namespace Innova;
const ROOT = __DIR__ . '/..';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/LeadRepository.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/LeadService.php';
$configFile = getenv('SEM_CONFIG') ?: ROOT . '/config/config.php';
$GLOBALS['innova_config'] = require (is_file($configFile) ? $configFile : ROOT . '/config/config.example.php');
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
set_exception_handler(function (\Throwable $error): void {
    safe_log('unhandled_' . str_replace('\\', '_', get_class($error)));
    if (PHP_SAPI === 'cli') { fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n"); exit(1); }
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        json_out(['ok' => false, 'code' => 'temporarily_unavailable', 'message' => 'No se ha podido confirmar el envío. Conserva tus datos y vuelve a intentarlo o llámanos.'], 503);
    }
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>InnovaPro</title><p>Servicio temporalmente no disponible. Puedes contactar en ' . esc(cfg('contact.phone_display')) . '.</p>';
});
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, private, max-age=0');
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $allowed = ['/', '/index.php', '/privacidad.php', '/cookies.php', '/aviso-legal.php', '/api/lead.php',
        '/admin/', '/admin/index.php', '/admin/lead.php', '/admin/export.php'];
    if (!in_array($path, $allowed, true)) { http_response_code(404); echo 'Página no encontrada.'; exit; }
    if (is_https()) { header('Strict-Transport-Security: max-age=31536000'); }
    // HTTP únicamente en loopback; no modos de aplicación ni flags de tests.
    $host = parse_url(origin(), PHP_URL_HOST);
    $loopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    if (!is_https() && !$loopback) { http_response_code(403); echo 'Esta aplicación requiere HTTPS.'; exit; }
}
