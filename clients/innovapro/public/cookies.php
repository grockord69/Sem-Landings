<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php'; require __DIR__.'/../src/view.php';
use function Innova\{head,header_view,footer_view,cfg,esc};
head('Política de cookies | InnovaPro'); header_view(false);
?>
<main id="contenido" class="wrap legal-page"><a href="/">← Volver a la landing</a><h1>Política de cookies</h1>
<p>Utilizamos Google Ads para medir qué anuncios generan contactos y mejorar nuestras campañas. Tu elección no impide enviar el formulario, llamar ni abrir WhatsApp.</p>
<h2>Cookies y almacenamiento</h2><div class="legal-scroll"><table><thead><tr><th>Elemento</th><th>Finalidad</th><th>Duración</th></tr></thead><tbody>
<tr><td>innova_consent</td><td>Cookie técnica propia para recordar aceptación o rechazo.</td><td><?= (int)cfg('consent.cookie_days') ?> días</td></tr>
<tr><td>_gcl_* de Google Ads</td><td>Atribución publicitaria, únicamente con aceptación; sin personalización.</td><td>Hasta 90 días según configuración y navegador.</td></tr>
<tr><td>sem:innova:pending-request y sem:innova:converted-leads</td><td>Almacenamiento técnico de sesión para evitar solicitudes y conversiones duplicadas. Solo identificadores, sin datos de contacto.</td><td>Sesión de la pestaña.</td></tr>
<tr><td>sem_admin</td><td>Sesión técnica privada para proteger acciones administrativas.</td><td>Sesión; solo /admin/.</td></tr>
</tbody></table></div>
<h2>Sin interacción o tras rechazo</h2><p>Usamos Consent Mode v2 avanzado: Google tag carga con ad_storage, ad_user_data, ad_personalization y analytics_storage denegados. Puede enviar pings de medición sin cookies publicitarias. No enviamos email ni teléfono para conversiones mejoradas.</p>
<h2>Al aceptar</h2><p>ad_storage y ad_user_data pasan a concedidos. ad_personalization y analytics_storage permanecen denegados. Solo en el formulario, tras guardarlo, pueden enviarse email y teléfono normalizados con hash para conversiones mejoradas. Más información en <a href="/privacidad.php">Privacidad</a>.</p>
<h2>Cambiar la elección</h2><p>Pulsa «Configurar cookies» en el footer. Al retirar la aceptación actualizamos el consentimiento y eliminamos las cookies publicitarias accesibles del subdominio. Puedes gestionar también cookies de otros dominios desde tu navegador.</p><p>Google facilita información sobre <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener noreferrer">sus tecnologías publicitarias</a>. No utilizamos Google Analytics, Google Tag Manager, otros trackers ni CMP externo. El panel privado y las páginas legales no cargan Google tag.</p>
</main><?php footer_view(); ?>
