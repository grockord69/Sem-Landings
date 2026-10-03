<?php declare(strict_types=1); use function Innova\{esc,local_date}; ?>
<h2>Nueva solicitud de depilación profesional · InnovaPro</h2>
<p><strong>ID interno:</strong> <?=esc($lead['id'])?><br>
<strong>Fecha y hora (Europe/Madrid):</strong> <?=esc(local_date($lead['created_at']))?><br>
<strong>Nombre:</strong> <?=esc($lead['nombre'])?><br>
<strong>Teléfono:</strong> <?=esc($lead['telefono'])?><br>
<strong>Email:</strong> <?=esc($lead['email'])?></p>
<p>Interés: información o demostración; compra, alquiler y financiación.</p>
<table>
<?php foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','wbraid','gbraid','landing_url','referrer'] as $key): if (!$lead[$key]) { continue; } ?>
<tr><th><?=esc($key)?></th><td><?=esc($lead[$key])?></td></tr>
<?php endforeach; ?>
</table>
<p>La solicitud está guardada en el panel privado. Responder a este correo contacta con la persona solicitante.</p>
