<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$currentAdmin = require_server_auth();

header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function admin_filters(): array
{
    $conditions = [];
    $params = [];
    $keep = [];

    foreach (['from', 'to'] as $key) {
        $date = is_string($_GET[$key] ?? null) ? $_GET[$key] : '';
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone((string) cfg('app.timezone')));
        if ($parsed && $parsed->format('Y-m-d') === $date) {
            $keep[$key] = $date;
            if ($key === 'to') {
                $parsed = $parsed->modify('+1 day');
            }
            $conditions[] = 'created_at ' . ($key === 'from' ? '>=' : '<') . ' ?';
            $params[] = $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
    }

    $search = clean_text($_GET, 'q', 100);
    if ($search !== '') {
        $conditions[] = "(nombre LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR telefono LIKE ? ESCAPE '!' OR utm_campaign LIKE ? ESCAPE '!' OR utm_source LIKE ? ESCAPE '!')";
        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
        array_push($params, $term, $term, $term, $term, $term);
        $keep['q'] = $search;
    }

    $status = clean_text($_GET, 'mail', 16);
    if (in_array($status, ['pending', 'sent', 'error'], true)) {
        $conditions[] = 'email_status = ?';
        $params[] = $status;
        $keep['mail'] = $status;
    }

    return [$conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', $params, $keep];
}

function mail_label(string $status): string
{
    return [
        'pending' => 'Pendiente',
        'sent' => 'Aceptado por SMTP',
        'error' => 'Error SMTP',
    ][$status] ?? $status;
}

function admin_head(string $title): void
{
    global $currentAdmin;
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= esc($title) ?> · InnovaPro</title>
  <link rel="stylesheet" href="/assets/css/admin.css?v=<?= (int) (@filemtime(__DIR__ . '/../assets/css/admin.css') ?: 1) ?>">
</head>
<body>
<header>
  <a href="/leadspanel/">INNOVAPRO <span>Panel privado</span></a>
  <div class="panel-user">
    <span><?= esc($currentAdmin) ?></span>
    <form action="/leadspanel/" method="post">
      <input type="hidden" name="csrf" value="<?= esc(panel_csrf_token()) ?>">
      <button type="submit" name="panel_logout" value="1">Salir</button>
    </form>
  </div>
</header>
<main>
<?php
}

function admin_foot(): void
{
    ?>
</main>
<footer>Horas <?= esc(cfg('app.timezone')) ?> · Acceso privado mediante sesión y contraseña cifrada.</footer>
</body>
</html>
<?php
}
