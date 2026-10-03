<?php
declare(strict_types=1);
namespace Innova;
require_once __DIR__ . '/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
function admin_user(): string {
    $users = cfg('admin.users', []);
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($user === '' && preg_match('/^Basic (.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '', $match)) {
        $decoded = base64_decode($match[1], true);
        if (is_string($decoded) && str_contains($decoded, ':')) { [$user, $pass] = explode(':', $decoded, 2); }
    }
    if (strlen($pass) <= 1024 && isset($users[$user]) && password_verify($pass, $users[$user])) { return $user; }
    header('WWW-Authenticate: Basic realm="InnovaPro privado", charset="UTF-8"');
    http_response_code(401); echo 'Se requiere usuario y contraseña.'; exit;
}
$currentUser = admin_user();
session_name('sem_admin');
session_set_cookie_params(['path' => '/admin/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_strict_mode', '1');
session_start();
if (($_SESSION['user'] ?? '') !== $currentUser) { session_regenerate_id(true); $_SESSION = ['user' => $currentUser]; }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function filters(): array {
    $sql = []; $params = []; $keep = [];
    foreach (['from', 'to'] as $key) {
        $date = is_string($_GET[$key] ?? null) ? $_GET[$key] : '';
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone((string)cfg('app.timezone')));
        if ($parsed && $parsed->format('Y-m-d') === $date) {
            $keep[$key] = $date;
            if ($key === 'to') { $parsed = $parsed->modify('+1 day'); }
            $sql[] = 'created_at ' . ($key === 'from' ? '>=' : '<') . ' ?';
            $params[] = $parsed->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
    }
    $search = text($_GET, 'q', 100);
    if ($search !== '') {
        $sql[] = "(nombre LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR telefono LIKE ? ESCAPE '!' OR utm_campaign LIKE ? ESCAPE '!' OR utm_source LIKE ? ESCAPE '!')";
        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
        array_push($params, $term, $term, $term, $term, $term); $keep['q'] = $search;
    }
    $status = text($_GET, 'mail', 16);
    if (in_array($status, ['pending', 'sent', 'error'], true)) { $sql[] = 'email_status=?'; $params[] = $status; $keep['mail'] = $status; }
    return [$sql ? ' WHERE ' . implode(' AND ', $sql) : '', $params, $keep];
}
function admin_head(string $title): void {
    global $currentUser;
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc($title) . ' · InnovaPro</title><link rel="stylesheet" href="/assets/css/admin.css"></head><body><header><a href="/admin/">INNOVAPRO <span>Panel privado</span></a><span>' . esc($currentUser) . '</span></header><main>';
}
function admin_foot(): void { echo '</main><footer>Horas ' . esc(cfg('app.timezone')) . ' · Cierra el navegador al terminar en un equipo compartido.</footer></body></html>'; }
function mail_label(string $status): string { return ['pending' => 'Pendiente', 'sent' => 'Aceptado por SMTP', 'error' => 'Error SMTP'][$status] ?? $status; }
