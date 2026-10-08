<?php
declare(strict_types=1);

function cfg(string $path, mixed $fallback = null): mixed
{
    $value = $GLOBALS['innovapro_config'] ?? [];
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $fallback;
        }
        $value = $value[$part];
    }
    return $value;
}

function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function api_error(string $code, string $message, int $status = 422, array $errors = []): never
{
    json_response([
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $errors,
    ], $status);
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        api_error('method', 'Método no permitido.', 405);
    }

    $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $expected = rtrim((string) cfg('app.url'), '/');
    if ($origin !== '' && $expected !== '' && !hash_equals($expected, $origin)) {
        api_error('origin', 'Origen no permitido.', 403);
    }
}

function request_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if ($raw === false || strlen($raw) > 32768) {
        api_error('size', 'Solicitud demasiado grande.', 413);
    }

    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType === 'application/json') {
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            api_error('json', 'Solicitud no válida.');
        }
    } elseif ($contentType === 'application/x-www-form-urlencoded') {
        parse_str($raw, $data);
    } else {
        api_error('content_type', 'Formato no admitido.', 415);
    }

    if (!is_array($data) || array_is_list($data)) {
        api_error('body', 'Solicitud no válida.');
    }

    return $data;
}

function clean_text(array $data, string $key, int $max): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    return iconv_substr($value, 0, $max, 'UTF-8') ?: '';
}

function normalize_phone(string $value): string
{
    $phone = preg_replace('/[\s().-]+/', '', trim($value)) ?? '';
    if (str_starts_with($phone, '00')) {
        $phone = '+' . substr($phone, 2);
    }
    if (preg_match('/^[6-9][0-9]{8}$/', $phone)) {
        $phone = '+34' . $phone;
    }
    return $phone;
}

function validate_lead(array $data): array
{
    $lead = [
        'nombre' => clean_text($data, 'name', 120),
        'telefono' => normalize_phone(clean_text($data, 'phone', 40)),
        'email' => strtolower(clean_text($data, 'email', 254)),
    ];

    $errors = [];
    if ($lead['nombre'] === '') {
        $errors['name'] = 'Indica tu nombre.';
    }
    if (!preg_match('/^\+?[0-9]{7,15}$/', $lead['telefono'])) {
        $errors['phone'] = 'Indica un teléfono válido.';
    }
    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Revisa el correo electrónico.';
    }
    if (!in_array($data['privacy'] ?? null, [true, 1, '1', 'on'], true)) {
        $errors['privacy'] = 'Autoriza el uso de tus datos para atender la solicitud.';
    }

    return [$lead, $errors];
}

function clean_url(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
        return '';
    }

    $base = $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? '/');

    parse_str($parts['query'] ?? '', $query);
    $allowed = [];
    foreach (['gclid', 'wbraid', 'gbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        if (is_string($query[$key] ?? null)) {
            $allowed[$key] = clean_text($query, $key, 512);
        }
    }

    return substr($base . ($allowed ? '?' . http_build_query($allowed) : ''), 0, 2048);
}

function attribution_data(array $data): array
{
    $values = is_array($data['attribution'] ?? null) ? $data['attribution'] : $data;
    $out = [];

    foreach (['gclid', 'wbraid', 'gbraid'] as $key) {
        $out[$key] = clean_text($values, $key, 512) ?: null;
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        $out[$key] = clean_text($values, $key, 200) ?: null;
    }

    $out['landing_url'] = clean_url(clean_text($values, 'landing_url', 2048)) ?: rtrim((string) cfg('app.url'), '/') . '/';
    $out['referrer'] = clean_url(clean_text($values, 'referrer', 2048)) ?: null;

    return $out;
}

function phone_link(): string
{
    return 'tel:' . preg_replace('/[^+0-9]/', '', (string) cfg('contact.phone_e164'));
}

function whatsapp_link(): string
{
    $number = preg_replace('/[^0-9]/', '', (string) cfg('contact.whatsapp_e164'));
    if (!preg_match('/^[1-9][0-9]{7,14}$/', $number)) {
        return '#contacto';
    }

    return 'https://wa.me/' . $number . '?text=' . rawurlencode((string) cfg('contact.whatsapp_message'));
}

function tracking_config(): array
{
    return [
        'ads' => cfg('ads'),
        'tracking' => true,
        'consent' => [
            'version' => cfg('consent.version'),
            'days' => cfg('consent.cookie_days'),
        ],
    ];
}

function local_date(?string $utc): string
{
    if (!$utc) {
        return '—';
    }

    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone((string) cfg('app.timezone', 'Europe/Madrid')))
        ->format('d/m/Y H:i:s');
}

function csv_cell(mixed $value): string
{
    $text = (string) $value;
    if (preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $text) || preg_match('/^[\t\r\n]/', $text)) {
        return "'" . $text;
    }
    return $text;
}

/**
 * Validación HTTP Basic usando un .htpasswd bcrypt privado.
 * Se hace en PHP para no requerir el AuthUserFile con ruta absoluta de Dinahosting.
 * No hay sesión ni segunda pantalla de login.
 */
function htpasswd_accepts(string $user, string $password, string $file): bool
{
    if ($user === '' || $password === '' || strlen($user) > 128 || strlen($password) > 256) {
        return false;
    }
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || count($lines) > 20) {
        return false;
    }
    foreach ($lines as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$name, $hash] = explode(':', trim($line), 2);
        if (hash_equals($name, $user) && str_starts_with($hash, '$2y$')) {
            return password_verify($password, $hash);
        }
    }
    return false;
}

function basic_auth_credentials(): array
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $password = $_SERVER['PHP_AUTH_PW'] ?? null;
    if (is_string($user) && is_string($password)) {
        return [$user, $password];
    }
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0 && is_string($value)) {
                $header = $value;
                break;
            }
        }
    }
    if (preg_match('/^Basic\\s+([A-Za-z0-9+\\/]+={0,2})$/iD', $header, $m)) {
        $decoded = base64_decode($m[1], true);
        if (is_string($decoded) && strlen($decoded) <= 512 && str_contains($decoded, ':')) {
            return explode(':', $decoded, 2);
        }
    }
    return ['', ''];
}

function require_server_auth(): string
{
    $file = __DIR__ . '/.htpasswd';
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'El panel no está activado: falta includes/.htpasswd.';
        exit;
    }
    [$user, $password] = basic_auth_credentials();
    if (htpasswd_accepts($user, $password, $file)) {
        return $user;
    }
    header('WWW-Authenticate: Basic realm="InnovaPro leads", charset="UTF-8"');
    header('Cache-Control: private, no-store');
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Credenciales incorrectas.';
    exit;
}
