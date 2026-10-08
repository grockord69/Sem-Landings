<?php
declare(strict_types=1);

/**
 * Sesiones privadas: independientes de HTTP Authorization en PHP-FPM.
 */
function panel_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''), 2)[0]);
    $secure = $host === 'demo.innovapro.es'
        || strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('innovapro_leads_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/leadspanel/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (@session_start() !== true) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pueden iniciar sesiones privadas en el alojamiento.';
        exit;
    }
}

function panel_csrf_token(): string
{
    panel_start_session();
    if (!is_string($_SESSION['panel_csrf'] ?? null)) {
        $_SESSION['panel_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['panel_csrf'];
}

function panel_login_page(string $message = ''): never
{
    $csrf = esc(panel_csrf_token());
    $version = (int) (@filemtime(__DIR__ . '/../assets/css/admin.css') ?: 1);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('Cache-Control: private, no-store, max-age=0');
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Acceso privado | InnovaPro</title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= $version ?>">
</head>
<body class="login-page">
<main class="login-wrap">
<section class="login-box" aria-labelledby="login-title">
<p class="login-brand">INNOVAPRO</p>
<h1 id="login-title">Acceso al panel de leads</h1>
<p>Introduce tu usuario y contraseña.</p>
<?php if ($message !== ''): ?><p class="login-error" role="alert"><?= esc($message) ?></p><?php endif; ?>
<form action="/leadspanel/" method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?= $csrf ?>">
<input type="hidden" name="panel_login" value="1">
<label for="user">Usuario</label>
<input id="user" name="username" autocomplete="username" required autofocus maxlength="128">
<label for="password">Contraseña</label>
<input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Entrar</button>
</form>
</section>
</main>
</body>
</html>
<?php
    exit;
}

function require_server_auth(): string
{
    $file = __DIR__ . '/.htpasswd';
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Falta includes/.htpasswd para activar el panel.';
        exit;
    }

    panel_start_session();
    $fingerprint = @hash_file('sha256', $file);
    if (!is_string($fingerprint)) {
        http_response_code(503);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['panel_logout'])) {
        if (!hash_equals(panel_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit;
        }
        unset($_SESSION['panel_user'], $_SESSION['panel_last_seen'], $_SESSION['panel_file_hash']);
        session_regenerate_id(true);
        header('Location: /leadspanel/', true, 303);
        exit;
    }

    $knownUser = $_SESSION['panel_user'] ?? null;
    $lastSeen = (int) ($_SESSION['panel_last_seen'] ?? 0);
    $knownHash = (string) ($_SESSION['panel_file_hash'] ?? '');
    if (is_string($knownUser) && $knownUser !== '' && $lastSeen > time() - 1800
        && hash_equals($fingerprint, $knownHash)) {
        $_SESSION['panel_last_seen'] = time();
        return $knownUser;
    }
    unset($_SESSION['panel_user'], $_SESSION['panel_last_seen'], $_SESSION['panel_file_hash']);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['panel_login'])) {
        $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        if (!hash_equals(panel_csrf_token(), $token)) {
            http_response_code(403);
            panel_login_page('La sesión ha caducado. Vuelve a intentarlo.');
        }
        $tries = (int) ($_SESSION['panel_login_failures'] ?? 0);
        if ($tries >= 10 && (int) ($_SESSION['panel_login_last_failure'] ?? 0) > time() - 300) {
            panel_login_page('Demasiados intentos. Espera cinco minutos.');
        }
        $user = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (htpasswd_accepts($user, $password, $file)) {
            if (!session_regenerate_id(true)) {
                http_response_code(503);
                exit;
            }
            $_SESSION['panel_user'] = $user;
            $_SESSION['panel_file_hash'] = $fingerprint;
            $_SESSION['panel_last_seen'] = time();
            unset($_SESSION['panel_login_failures'], $_SESSION['panel_login_last_failure']);
            header('Location: /leadspanel/', true, 303);
            exit;
        }
        $_SESSION['panel_login_failures'] = $tries + 1;
        $_SESSION['panel_login_last_failure'] = time();
        panel_login_page('Usuario o contraseña incorrectos.');
    }

    panel_login_page();
}
