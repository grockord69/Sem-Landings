<?php
declare(strict_types=1);
namespace Innova;

function cfg(string $path, mixed $fallback = null): mixed {
    $value = $GLOBALS['innova_config'] ?? [];
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) { return $fallback; }
        $value = $value[$part];
    }
    return $value;
}
function esc(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function safe_log(string $code): void { error_log('[sem] ' . preg_replace('/[^a-zA-Z0-9_]/', '_', $code)); }
function is_https(): bool {
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || (in_array($_SERVER['REMOTE_ADDR'] ?? '', cfg('app.trusted_proxy_ips', []), true)
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function origin(): string { return rtrim((string)cfg('app.url'), '/'); }
function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function fail(string $code, string $message, int $status = 422, array $errors = []): never {
    json_out(['ok' => false, 'code' => $code, 'message' => $message, 'errors' => $errors], $status);
}
function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); fail('method', 'Método no permitido.', 405); }
    // Aceptar solicitudes sin Origin (p.ej. formulario nativo); no es un filtro antispam.
    $provided = rtrim($_SERVER['HTTP_ORIGIN'] ?? '', '/');
    if ($provided !== '' && !hash_equals(origin(), $provided)) { fail('origin', 'Origen no permitido.', 403); }
}
function body(): array {
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if ($raw === false || strlen($raw) > 32768) { fail('size', 'Solicitud demasiado grande.', 413); }
    $ct = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($ct === 'application/json') {
        try { $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); } catch (\JsonException) { fail('json', 'Solicitud no válida.'); }
    } elseif ($ct === 'application/x-www-form-urlencoded') { parse_str($raw, $data); }
    else { fail('content_type', 'Formato no admitido.', 415); }
    if (!is_array($data) || array_is_list($data)) { fail('body', 'Solicitud no válida.'); }
    return $data;
}
function text(array $data, string $key, int $max): string {
    $s = $data[$key] ?? '';
    if (!is_string($s)) { return ''; }
    $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '');
    return iconv_substr($s, 0, $max, 'UTF-8') ?: '';
}
function normalize_phone(string $value): string {
    $phone = preg_replace('/[\s().-]+/', '', trim($value)) ?? '';
    if (str_starts_with($phone, '00')) { $phone = '+' . substr($phone, 2); }
    // Esta landing se dirige a España; nueve dígitos nacionales llevan +34.
    if (preg_match('/^[6-9][0-9]{8}$/', $phone)) { $phone = '+34' . $phone; }
    return $phone;
}
function validate_lead(array $data): array {
    $lead = ['nombre' => text($data, 'name', 120), 'email' => strtolower(text($data, 'email', 254)),
        'telefono' => normalize_phone(text($data, 'phone', 40))];
    $errors = [];
    if ($lead['nombre'] === '') { $errors['name'] = 'Indica tu nombre.'; }
    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Revisa el correo electrónico.'; }
    if (!preg_match('/^\+?[0-9]{7,15}$/', $lead['telefono'])) { $errors['phone'] = 'Indica un teléfono válido.'; }
    if (!in_array($data['privacy'] ?? null, [true, 1, '1', 'on'], true)) { $errors['privacy'] = 'Autoriza el uso de tus datos para atender la solicitud.'; }
    return [$lead, $errors];
}
function attribution(array $data): array {
    $values = is_array($data['attribution'] ?? null) ? $data['attribution'] : $data;
    $out = [];
    foreach (['gclid', 'wbraid', 'gbraid'] as $key) { $out[$key] = text($values, $key, 512) ?: null; }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) { $out[$key] = text($values, $key, 200) ?: null; }
    $out['landing_url'] = clean_url(text($values, 'landing_url', 2048)) ?: origin() . '/';
    $out['referrer'] = clean_url(text($values, 'referrer', 2048)) ?: null;
    return $out;
}
function clean_url(string $url): string {
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host'])) { return ''; }
    $base = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '/');
    // No conservar parámetros arbitrarios que podrían contener datos personales.
    parse_str($parts['query'] ?? '', $query);
    $allowed = [];
    foreach (['gclid', 'wbraid', 'gbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        if (is_string($query[$key] ?? null)) { $allowed[$key] = text($query, $key, 512); }
    }
    return substr($base . ($allowed ? '?' . http_build_query($allowed) : ''), 0, 2048);
}
function privacy_statement(): string { return 'He leído la Política de Privacidad y autorizo a InnovaPro a utilizar mis datos para atender esta solicitud.'; }
function client_config(bool $tracking): array {
    return ['ads' => cfg('ads'), 'tracking' => $tracking,
        'consent' => ['version' => cfg('consent.version'), 'days' => cfg('consent.cookie_days')]];
}
function phone_link(): string { return 'tel:' . preg_replace('/[^+0-9]/', '', (string)cfg('contact.phone_e164')); }
function whatsapp_link(): string {
    $number = preg_replace('/[^0-9]/', '', (string)cfg('contact.whatsapp_e164'));
    return preg_match('/^[1-9][0-9]{7,14}$/', $number) ? 'https://wa.me/' . $number . '?text=' . rawurlencode((string)cfg('contact.whatsapp_message')) : '#contacto';
}
function csv_cell(mixed $value): string {
    $s = (string)$value;
    return preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $s) || preg_match('/^[\t\r\n]/', $s) ? "'" . $s : $s;
}
function local_date(string $utc): string {
    return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone((string)cfg('app.timezone')))->format('d/m/Y H:i:s');
}
