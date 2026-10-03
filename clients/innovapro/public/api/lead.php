<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
use Innova\LeadService;
use function Innova\{require_post,body,validate_lead,text,fail,attribution,db,json_out};
require_post();
$data = body();
[$lead, $errors] = validate_lead($data);
if ($errors) { fail('validation', 'Revisa los campos señalados.', 422, $errors); }
$requestId = text($data, 'request_id', 64);
if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) { fail('request_id', 'Identificador de solicitud no válido. Recarga la página conservando tus datos.'); }
try { $result = (new LeadService(db()))->submit($lead, $requestId, attribution($data)); }
catch (\DomainException) { fail('idempotency_conflict', 'Esta solicitud ya fue enviada con otros datos. Recarga la página para iniciar otra.', 409); }
json_out(['ok' => true] + $result + ['message' => 'Solicitud recibida. Nuestro equipo contactará contigo.']);
