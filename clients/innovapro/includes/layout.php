<?php
declare(strict_types=1);

function page_head(string $title, bool $tracking = false): void
{
    $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    $google = "https://www.googletagmanager.com https://www.googleadservices.com https://googleads.g.doubleclick.net https://www.google.com https://www.google.es https://*.googleadservices.com https://*.googlesyndication.com https://*.doubleclick.net";

    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce' https://www.googletagmanager.com https://www.googleadservices.com https://www.google.com https://googleads.g.doubleclick.net; style-src 'self'; img-src 'self' data: $google; connect-src 'self' $google; frame-src https://www.googletagmanager.com https://td.doubleclick.net https://www.google.com https://www.google.es; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('X-Robots-Tag: noindex, follow');

    $config = tracking_config();
    $config['tracking'] = $tracking;
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title><?= esc($title) ?></title>
  <meta name="description" content="Equipos de depilación profesional InnovaPro. Gama SHR y láser diodo. Compra, alquiler y financiación. Solicita una demostración sin compromiso.">
  <link rel="stylesheet" href="/assets/css/site.css">
  <script id="innova-config" type="application/json" nonce="<?= esc($nonce) ?>"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?></script>
  <script src="/assets/js/consent.js" defer></script>
  <script src="/assets/js/tracking.js" defer></script>
  <script src="/assets/js/app.js?v=<?= (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 1) ?>" defer></script>
</head>
<body>
<?php
}

function whatsapp_button(string $class = 'button button-secondary', string $label = 'WhatsApp'): string
{
    $url = whatsapp_link();
    $ready = $url !== '#contacto';

    return '<a class="' . esc($class) . '" href="' . esc($url) . '" '
        . ($ready
            ? 'data-contact="whatsapp" target="_blank" rel="noopener noreferrer"'
            : 'data-whatsapp-unconfigured="1" aria-label="WhatsApp pendiente de configuración; solicitar información"')
        . '>' . esc($label) . '<span aria-hidden="true"> ↗</span></a>';
}

function site_header(bool $landing = true): void
{
    ?>
<a class="skip" href="#contenido">Saltar al contenido</a>
<div class="announcement">Prueba nuestra aparatología sin compromiso</div>
<header class="site-header wrap">
  <span class="header-spacer" aria-hidden="true"></span>
  <a class="brand" href="<?= $landing ? '#contenido' : '/' ?>" aria-label="InnovaPro, inicio">
    <img src="/assets/img/logo.png" width="178" height="50" alt="InnovaPro">
  </a>
  <div class="header-contact">
    <a class="phone-text" href="<?= esc(phone_link()) ?>" data-contact="phone"><?= esc(cfg('contact.phone_display')) ?></a>
    <?= whatsapp_button('header-whatsapp') ?>
  </div>
</header>
<?php
}

function site_footer(bool $mobileBar = false): void
{
    ?>
<footer class="site-footer">
  <div class="wrap footer-main">
    <img src="/assets/img/logo.png" width="132" height="37" alt="InnovaPro">
    <div>
      <a class="footer-phone" href="<?= esc(phone_link()) ?>" data-contact="phone"><?= esc(cfg('contact.phone_display')) ?></a><br>
      <a href="mailto:<?= esc(cfg('contact.email')) ?>"><?= esc(cfg('contact.email')) ?></a>
    </div>
    <p><?= esc(cfg('legal.address')) ?></p>
  </div>
  <div class="wrap footer-legal">
    <span>© <?= gmdate('Y') ?> InnovaPro</span>
    <div>
      <a href="/aviso-legal.php" target="_blank" rel="noopener noreferrer">Aviso legal</a>
      <a href="/privacidad.php" target="_blank" rel="noopener noreferrer">Privacidad</a>
      <a href="/cookies.php" target="_blank" rel="noopener noreferrer">Cookies</a>
      <button type="button" data-consent-open>Configurar cookies</button>
    </div>
  </div>
</footer>
<?php if ($mobileBar): ?>
<nav class="mobile-contact" aria-label="Contactar con InnovaPro">
  <a href="#contacto" class="mobile-primary" data-form-focus>Solicitar demo</a>
  <a href="<?= esc(phone_link()) ?>" data-contact="phone">Llamar</a>
  <?= whatsapp_button('mobile-whatsapp') ?>
</nav>
<?php endif; ?>
<aside id="consent-banner" class="consent-banner" aria-labelledby="consent-title" hidden>
  <div class="consent-inner">
    <div>
      <strong id="consent-title">Cookies y medición</strong>
      <p id="consent-description"></p>
      <a href="/cookies.php" target="_blank" rel="noopener noreferrer">Más información</a>
    </div>
    <div class="consent-actions">
      <button type="button" data-consent-reject>Rechazar</button>
      <button type="button" data-consent-accept>Aceptar</button>
      <button type="button" class="consent-close" data-consent-close hidden>Cerrar sin cambiar</button>
    </div>
  </div>
</aside>
</body>
</html>
<?php
}
