<?php
/**
 * Copia este archivo como includes/config.php y completa los datos privados.
 * Nunca subas el config.php real al repositorio.
 */
declare(strict_types=1);

return [
    'app' => [
        'url' => 'https://demo.innovapro.es',
        'timezone' => 'Europe/Madrid',
        'privacy_version' => 'innova-2026-10-07-v1',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'password' => '',
        'ssl_ca' => '',
    ],

    'smtp' => [
        'host' => '',
        'port' => 587,
        'encryption' => 'tls', // tls (STARTTLS) o ssl (SMTPS)
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => 'InnovaPro',
        'recipients' => [], // ['comercial@innovapro.es']
        'timeout_seconds' => 6,
    ],

    'contact' => [
        'phone_display' => '968 603 256',
        'phone_e164' => '+34968603256',
        'whatsapp_e164' => '+34606681297',
        'email' => 'info@innovapro.es',
        'whatsapp_message' => 'Hola, me gustaría recibir información sobre los equipos de depilación profesional de InnovaPro: compra, alquiler y financiación.',
    ],

    // IDs públicos de Google Ads.
    'ads' => [
        'id' => 'AW-763034950',
        'conversions' => [
            'form' => 'AW-763034950/bu_ECPvC9PkBEMb66-sC',
            'phone' => 'AW-763034950/evT9CI2-wfwZEMb66-sC',
            'whatsapp' => 'AW-763034950/a58xCJOfqo8dEMb66-sC',
        ],
        'values' => [
            'form' => 30.0,
            'phone' => 10.0,
            'whatsapp' => 10.0,
        ],
        'currency' => 'EUR',
    ],

    'consent' => [
        'version' => 'innova-cmp-simple-1',
        'cookie_days' => 180,
    ],

    'legal' => [
        'company' => 'SHR LAXER BUSINESS S.L.',
        'tax_id' => 'B-54819321',
        'address' => 'Calle Número 16, naves 39 / 41. Pol. Industrial Base 2000. 30564 Lorquí, Murcia.',
        'registry' => 'Tomo 3814, libro 0, folio 45, sección 8, hoja A 142356, inscripción 1, de 18/11/2014.',
        'privacy_email' => 'info@innovapro.es',
        'hosting_provider' => '',
        'email_provider' => '',
        'retention_policy' => '',
    ],

    'content' => [
        'testimonials_enabled' => true,
    ],
];
