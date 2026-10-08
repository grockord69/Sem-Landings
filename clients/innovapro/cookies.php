<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
page_head('Política de cookies | InnovaPro'); site_header(false);
?>
<main id="contenido" class="wrap legal-page"><a href="/">← Volver a la landing</a><h1>Política de cookies</h1>
<p>Utilizamos Google Ads para medir qué anuncios generan contactos y mejorar nuestras campañas. Tu elección no impide enviar el formulario, llamar ni abrir WhatsApp.</p>
<h2>Cookies y almacenamiento</h2><div class="legal-scroll"><table><thead><tr><th>Elemento</th><th>Finalidad</th><th>Duración</th></tr></thead><tbody>
<tr><td>innova_consent</td><td>Cookie propia de InnovaPro, necesaria para recordar la aceptación o el rechazo de la medición publicitaria.</td><td><?= (int)cfg('consent.cookie_days') ?> días</td></tr>
<tr><td>_gcl_* de Google Ads (Google Ireland Limited)</td><td>Cookies publicitarias de atribución y medición de conversiones, que pueden instalarse únicamente si aceptas. No habilitamos personalización de anuncios.</td><td>Hasta 90 días, según la información publicada por Google.</td></tr>
<tr><td>innovapro_leads_sid</td><td>Cookie técnica de sesión del panel privado, necesaria para mantener el acceso autorizado. No se instala a visitantes de la landing.</td><td>Sesión de navegador; el acceso caduca tras 30 minutos de inactividad.</td></tr>
<tr><td>sem:innova:pending-request y sem:innova:converted-leads</td><td>Almacenamiento técnico de sesión para evitar solicitudes y conversiones duplicadas. Solo identificadores, sin datos de contacto.</td><td>Sesión de la pestaña.</td></tr>

</tbody></table></div>
<h2>Sin interacción o tras rechazo</h2><p>Usamos Consent Mode v2 avanzado: Google tag carga con ad_storage, ad_user_data, ad_personalization y analytics_storage denegados. Google puede recibir señales técnicas de consentimiento y conversión sin cookies publicitarias, incluso si no aceptas o rechazas; estas señales pueden incluir datos técnicos de navegación y de la página, pero no el email ni el teléfono facilitados en el formulario para conversiones mejoradas. Rechazar impide el almacenamiento publicitario opcional, no equivale a impedir toda transmisión técnica a Google.</p>
<h2>Al aceptar</h2><p>ad_storage y ad_user_data pasan a concedidos. ad_personalization y analytics_storage permanecen denegados. Solo en el formulario, tras guardarlo, pueden enviarse email y teléfono normalizados con hash para conversiones mejoradas. Más información en <a href="/privacidad.php">Privacidad</a>.</p>
<h2>Responsable y tercero</h2><p>El responsable de esta página es <?=esc(cfg('legal.company'))?>. El proveedor de las tecnologías publicitarias es Google Ireland Limited. La duración y los nombres efectivos de las cookies pueden variar con las actualizaciones de Google y del navegador; el inventario se revisa tras el despliegue y ante cambios de tecnología. Consulta <a href="https://policies.google.com/technologies/cookies?hl=es" target="_blank" rel="noopener noreferrer">cómo usa Google las cookies</a>.</p><h2>Cambiar la elección</h2><p>Pulsa «Configurar cookies» en el footer. Al retirar la aceptación actualizamos el consentimiento y eliminamos las cookies publicitarias accesibles del subdominio. Puedes gestionar también cookies de otros dominios desde tu navegador.</p><p>Google facilita información sobre <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener noreferrer">sus tecnologías publicitarias</a>. No utilizamos Google Analytics, Google Tag Manager, otros trackers ni CMP externo. El panel privado y las páginas legales no cargan Google tag.</p>
</main><?php site_footer(); ?>
