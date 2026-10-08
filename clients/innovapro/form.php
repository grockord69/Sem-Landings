<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_post();
$data = request_body();
[$lead, $errors] = validate_lead($data);

if ($errors) {
    api_error('validation', 'Revisa los campos señalados.', 422, $errors);
}

$requestId = clean_text($data, 'request_id', 64);
if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) {
    api_error('request_id', 'Identificador de solicitud no válido. Recarga la página conservando tus datos.');
}

try {
    $result = submit_lead($lead, $requestId, attribution_data($data));
} catch (DomainException) {
    api_error('idempotency_conflict', 'Esta solicitud ya fue enviada con otros datos. Recarga la página para iniciar otra.', 409);
}

$payload = [
    'ok' => true,
    'lead_id' => $result['lead_id'],
    'duplicate' => $result['duplicate'],
    'message' => 'Solicitud recibida. Nuestro equipo contactará contigo.',
];

$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
if (str_contains($accept, 'application/json') || str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    json_response($payload);
}

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Solicitud recibida · InnovaPro</title></head>
<body><main><h1>Solicitud recibida</h1><p>Nuestro equipo contactará contigo.</p><p><a href="/">Volver a InnovaPro</a></p></main></body>
</html>
