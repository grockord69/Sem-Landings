<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
    $checks++;
    echo "OK $label\n";
}

[$lead, $errors] = validate_lead([
    'name' => '  María  ',
    'phone' => '600 111 222',
    'email' => ' PERSON@EXAMPLE.COM ',
    'privacy' => true,
]);
check(!$errors, 'lead válido');
check($lead['nombre'] === 'María', 'nombre normalizado');
check($lead['telefono'] === '+34600111222', 'teléfono español E.164');
check($lead['email'] === 'person@example.com', 'email normalizado');

[, $errors] = validate_lead(['name' => '', 'phone' => 'x', 'email' => 'x', 'privacy' => false]);
check(array_keys($errors) === ['name', 'phone', 'email', 'privacy'], 'errores de validación completos');

check(normalize_phone('0034600111222') === '+34600111222', 'prefijo 00');
check(clean_url('https://demo.innovapro.es/?gclid=abc&utm_campaign=x&email=secret') === 'https://demo.innovapro.es/?gclid=abc&utm_campaign=x', 'URL allowlist');
check(clean_url('javascript:alert(1)') === '', 'URL insegura descartada');
check(phone_link() === 'tel:+34968603256', 'enlace de teléfono');
check(str_starts_with(whatsapp_link(), 'https://wa.me/34606681297'), 'enlace WhatsApp');

check(cfg('ads.values.form') === 30.0, 'valor formulario 30 EUR');
check(cfg('ads.values.phone') === 10.0, 'valor llamada 10 EUR');
check(cfg('ads.values.whatsapp') === 10.0, 'valor WhatsApp 10 EUR');
check(cfg('ads.currency') === 'EUR', 'moneda EUR');

check(csv_cell('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'CSV neutraliza fórmulas');
check(csv_cell('normal') === 'normal', 'CSV conserva texto normal');

check(smtp_is_configured() === false, 'ejemplo no finge SMTP configurado');
check((string)cfg('legal.retention_policy') !== '', 'conservación basada en criterios existentes de InnovaPro');
check(cfg('legal.hosting_provider') === '' && cfg('legal.email_provider') === '', 'no inventa proveedores de la instalación');

echo "TOTAL $checks comprobaciones PHP\n";
