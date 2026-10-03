<?php
/** Copiar a config.php, fuera de public; también admite una ruta externa en SEM_CONFIG. */
declare(strict_types=1);
return [
    'app' => [
        'url' => 'https://demo.innovapro.es',
        'timezone' => 'Europe/Madrid',
        'privacy_version' => 'innova-2026-10-03-v2',
        'trusted_proxy_ips' => [], // Solo proxies que sobrescriben X-Forwarded-Proto.
    ],
    'db' => [
        'host' => '127.0.0.1', 'port' => 3306, 'name' => '',
        'user' => '', 'password' => '', 'ssl_ca' => '',
    ],
    'smtp' => [
        'host' => '', 'port' => 587, 'encryption' => 'tls',
        'username' => '', 'password' => '',
        'from_email' => '', 'from_name' => 'InnovaPro', 'recipients' => [],
        'timeout_seconds' => 5,
    ],
    'admin' => [
        'users' => [], // ['usuario' => HASH]; generar con php bin/console.php password
    ],
    'contact' => [
        'phone_display' => '968 603 256', 'phone_e164' => '+34968603256',
        'whatsapp_e164' => '+34606681297',
        'email' => 'info@innovapro.es',
        'whatsapp_message' => 'Hola, me gustaría recibir información sobre los equipos de depilación profesional de InnovaPro: compra, alquiler y financiación.',
    ],
    // Única fuente de IDs; son públicos, no credenciales.
    'ads' => [
        'id' => 'AW-763034950',
        'conversions' => [
            'form' => 'AW-763034950/bu_ECPvC9PkBEMb66-sC',
            'phone' => 'AW-763034950/evT9CI2-wfwZEMb66-sC',
            'whatsapp' => 'AW-763034950/a58xCJOfqo8dEMb66-sC',
        ],
        'values' => ['form' => 30.0, 'phone' => 10.0, 'whatsapp' => 10.0], 'currency' => 'EUR',
    ],
    'consent' => ['version' => 'innova-cmp-2', 'cookie_days' => 180],
    'legal' => [
        // Verificados en el aviso legal oficial; ver docs/SOURCES.md.
        'company' => 'SHR LAXER BUSINESS S.L.', 'tax_id' => 'B-54819321',
        'address' => 'Calle Número 16, naves 39 / 41. Pol. Industrial Base 2000. 30564 Lorquí, Murcia.',
        'registry' => 'Tomo 3814, libro 0, folio 45, sección 8, hoja A 142356, inscripción 1, de 18/11/2014.',
        'privacy_email' => 'info@innovapro.es',
        'hosting_provider' => '', 'email_provider' => '',
        'retention_policy' => '', // Pendiente de confirmar, no inventar un plazo.
    ],
    'content' => ['testimonials_enabled' => true],
];
