<?php
declare(strict_types=1);

require __DIR__ . '/../includes/admin.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$lead = find_lead($id);
if (!$lead) {
    http_response_code(404);
    echo 'Solicitud no encontrada.';
    exit;
}

admin_head('Detalle de solicitud');
?>
<p><a href="/admin/">← Volver a solicitudes</a></p>
<h1><?= esc($lead['nombre']) ?></h1>
<p><?= esc(local_date($lead['created_at'])) ?> · ID interno #<?= $id ?></p>

<dl class="detail">
  <dt>Teléfono</dt><dd><a href="tel:<?= esc($lead['telefono']) ?>"><?= esc($lead['telefono']) ?></a></dd>
  <dt>Email</dt><dd><a href="mailto:<?= esc($lead['email']) ?>"><?= esc($lead['email']) ?></a></dd>
  <dt>Correo SMTP</dt><dd><?= esc(mail_label($lead['email_status'])) ?> · <?= (int) $lead['email_attempts'] ?> intentos<?= $lead['email_last_error'] ? ' · ' . esc($lead['email_last_error']) : '' ?></dd>
  <dt>Aceptado por SMTP</dt><dd><?= esc(local_date($lead['email_sent_at'])) ?></dd>
  <dt>Privacidad</dt><dd>Consentimiento: <?= (int) $lead['consent_rgpd'] === 1 ? 'Sí' : 'No' ?> · Versión: <?= esc($lead['consent_version']) ?></dd>
  <?php foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'wbraid', 'gbraid', 'landing_url', 'referrer'] as $key): ?>
    <dt><?= esc($key) ?></dt><dd><?= esc($lead[$key] ?? '—') ?></dd>
  <?php endforeach; ?>
</dl>

<?php if ($lead['email_status'] === 'error'): ?>
<p class="notice"><strong>El lead está guardado correctamente.</strong> La notificación por correo falló; consulta los datos aquí y revisa la configuración SMTP.</p>
<?php endif; ?>

<?php admin_foot(); ?>
