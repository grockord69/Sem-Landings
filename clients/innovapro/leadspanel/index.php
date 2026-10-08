<?php
declare(strict_types=1);

require __DIR__ . '/../includes/admin.php';

[$where, $params, $keep] = admin_filters();

$count = db()->prepare('SELECT COUNT(*) FROM leads' . $where);
$count->execute($params);
$total = (int) $count->fetchColumn();

$pages = max(1, (int) ceil($total / 50));
$page = max(1, min((int) ($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * 50;

$query = db()->prepare('SELECT * FROM leads' . $where . ' ORDER BY id DESC LIMIT 50 OFFSET ' . $offset);
$query->execute($params);
$rows = $query->fetchAll();

admin_head('Solicitudes');
?>
<div class="title-row">
  <div>
    <h1>Solicitudes</h1>
    <p>Formularios almacenados. No equivale al total atribuido por Google Ads.</p>
  </div>
  <a class="button" href="export.php?<?= esc(http_build_query($keep)) ?>">Exportar CSV</a>
</div>

<form method="get" class="filters">
  <label>Desde<input type="date" name="from" value="<?= esc($keep['from'] ?? '') ?>"></label>
  <label>Hasta<input type="date" name="to" value="<?= esc($keep['to'] ?? '') ?>"></label>
  <label>Buscar<input name="q" value="<?= esc($keep['q'] ?? '') ?>" placeholder="Nombre, email, teléfono o campaña"></label>
  <label>Correo
    <select name="mail">
      <option value="">Todos</option>
      <?php foreach (['pending', 'sent', 'error'] as $state): ?>
        <option value="<?= esc($state) ?>" <?= ($keep['mail'] ?? '') === $state ? 'selected' : '' ?>><?= esc(mail_label($state)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button>Filtrar</button>
  <a href="index.php">Limpiar</a>
</form>

<p><strong><?= $total ?></strong> solicitudes encontradas.</p>

<div class="table-wrap">
<table>
  <thead><tr><th>Fecha</th><th>Nombre</th><th>Teléfono / email</th><th>Origen / campaña</th><th>Correo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= esc(local_date($row['created_at'])) ?></td>
      <td><?= esc($row['nombre']) ?></td>
      <td><?= esc($row['telefono']) ?><br><?= esc($row['email']) ?></td>
      <td><?= esc($row['utm_source'] ?? '') ?><br><?= esc($row['utm_campaign'] ?? ($row['gclid'] ? 'Google Ads' : 'Sin campaña')) ?></td>
      <td><span class="badge <?= esc($row['email_status']) ?>"><?= esc(mail_label($row['email_status'])) ?></span></td>
      <td><a href="lead.php?id=<?= (int) $row['id'] ?>">Ver</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6">No hay solicitudes con estos filtros.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<nav class="pagination" aria-label="Paginación">
  <?php if ($page > 1): ?><a href="?<?= esc(http_build_query($keep + ['page' => $page - 1])) ?>">Anterior</a><?php endif; ?>
  <span>Página <?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="?<?= esc(http_build_query($keep + ['page' => $page + 1])) ?>">Siguiente</a><?php endif; ?>
</nav>

<p class="notice">«Aceptado por SMTP» confirma que el servidor de correo aceptó el mensaje; no garantiza su llegada a bandeja de entrada.</p>
<?php admin_foot(); ?>
