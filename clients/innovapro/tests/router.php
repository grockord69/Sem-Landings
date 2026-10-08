<?php
declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (preg_match('#^/(?:includes|lib|sql|tests|docs)(?:/|$)#i', $path) || preg_match('#(?:^|/)\.#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$root = dirname(__DIR__);
$file = $root . $path;

// La ruta antigua debe quedar inutilizada tras la migración.
if (preg_match('#^/admin(?:/|$)#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
// PHP comprueba ahora una contraseña bcrypt real; no se simula REMOTE_USER.
if ($path === '/leadspanel/' && is_file($root . '/leadspanel/index.php')) {
    require $root . '/leadspanel/index.php';
    return true;
}

if ($path !== '/' && is_file($file)) {
    return false;
}
if ($path === '/' || $path === '') {
    require dirname(__DIR__) . '/index.php';
    return true;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    require rtrim($file, '/') . '/index.php';
    return true;
}

http_response_code(404);
echo 'Página no encontrada.';
return true;
