<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
use function Innova\{validate_lead,normalize_phone,clean_url,csv_cell,esc,attribution};
$checks = 0;
function check(bool $condition, string $message): void { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; echo "OK $message\n"; }
$valid = ['name' => 'Á', 'phone' => '600 111 222', 'email' => ' PERSONA@EXAMPLE.TEST ', 'privacy' => true];
[$lead, $errors] = validate_lead($valid);
check(!$errors && $lead['nombre'] === 'Á' && $lead['email'] === 'persona@example.test' && $lead['telefono'] === '+34600111222', 'nombre Unicode corto, email y teléfono normalizados');
foreach (['name','phone','email','privacy'] as $field) {
    [, $errors] = validate_lead(array_replace($valid, [$field => $field === 'privacy' ? false : '']));
    check(isset($errors[$field]), 'rechaza ausencia de ' . $field);
}
check(normalize_phone('0034 (600) 111-222') === '+34600111222', 'prefijo 00');
check(normalize_phone('+44 7700 900123') === '+447700900123', 'internacional conservado');
check(esc('<script>"&') === '&lt;script&gt;&quot;&amp;', 'escape HTML');
foreach (['=CMD()', '+34600111222', "\t@formula", '-1+1'] as $value) { check(str_starts_with(csv_cell($value), "'"), 'CSV neutraliza fórmulas'); }
check(csv_cell('Nombre normal') === 'Nombre normal', 'CSV conserva texto');
check(clean_url('javascript:alert(1)') === '', 'URL no HTTP rechazada');
check(clean_url('https://example.test/?email=persona@example.test&utm_source=google#secret') === 'https://example.test/?utm_source=google', 'URL elimina parámetros personales y fragmento');
$attr = attribution(['attribution' => ['gclid' => 'click-123', 'utm_campaign' => 'demo', 'referrer' => 'https://example.test/?secret=value']]);
check($attr['gclid'] === 'click-123' && $attr['utm_campaign'] === 'demo' && $attr['referrer'] === 'https://example.test/', 'metadatos de solicitud sin persistencia de IP');
echo "TOTAL $checks comprobaciones\n";
