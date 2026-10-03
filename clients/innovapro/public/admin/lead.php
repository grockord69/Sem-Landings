<?php
declare(strict_types=1);
require __DIR__ . '/../../src/admin.php';
use Innova\{LeadRepository,LeadService};
use function Innova\{db,esc,admin_head,admin_foot,mail_label,local_date};
$id = max(0, (int)($_GET['id'] ?? 0));
$repository = new LeadRepository(db());
$lead = $repository->find($id);
if (!$lead) { http_response_code(404); echo 'Solicitud no encontrada.'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']) || ($_POST['action'] ?? '') !== 'retry_email') {
        http_response_code(403); echo 'Acción no autorizada.'; exit;
    }
    // Solo error; mismo lead, sin scripts Ads, sin insertar ni modificar la conversión.
    (new LeadService(db()))->notify($id, true);
    header('Location: /admin/lead.php?id=' . $id, true, 303); exit;
}
admin_head('Detalle de solicitud');
?>
<p><a href="/admin/">← Volver a solicitudes</a></p><h1><?=esc($lead['nombre'])?></h1><p><?=esc(local_date($lead['created_at']))?> · ID interno #<?=$id?></p>
<dl class="detail"><dt>Teléfono</dt><dd><a href="tel:<?=esc($lead['telefono'])?>"><?=esc($lead['telefono'])?></a></dd><dt>Email</dt><dd><a href="mailto:<?=esc($lead['email'])?>"><?=esc($lead['email'])?></a></dd><dt>Correo SMTP</dt><dd><?=esc(mail_label($lead['email_status']))?> · <?=(int)$lead['email_attempts']?> intentos<?= $lead['email_last_error'] ? ' · ' . esc($lead['email_last_error']) : ''?></dd><dt>Aceptado por SMTP</dt><dd><?= $lead['email_sent_at'] ? esc(local_date($lead['email_sent_at'])) : '—' ?></dd><dt>Privacidad</dt><dd>Consentimiento: <?= (int)$lead['consent_rgpd'] === 1 ? 'Sí' : 'No' ?> · Versión: <?=esc($lead['consent_version'])?></dd>
<?php foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','wbraid','gbraid','landing_url','referrer'] as $key): ?><dt><?=esc($key)?></dt><dd><?=esc($lead[$key] ?? '—')?></dd><?php endforeach; ?></dl>
<?php if ($lead['email_status'] === 'error'): ?>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="retry_email"><button type="submit">Reenviar notificación SMTP</button></form>
<p>Se reintenta el correo de esta solicitud. No crea otra solicitud ni envía conversiones.</p>
<?php endif; admin_foot(); ?>
