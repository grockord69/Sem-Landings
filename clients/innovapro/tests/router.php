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

// El servidor PHP integrado no aplica Basic Auth de Apache. Para la suite,
// emulamos exclusivamente el REMOTE_USER que Plesk/Apache establecerá tras validar.
if (str_starts_with($path, '/admin/')) {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Basic\s+(.+)$/i', $authorization, $match)) {
        $decoded = base64_decode($match[1], true);
        if (is_string($decoded) && str_contains($decoded, ':')) {
            [$_SERVER['REMOTE_USER']] = explode(':', $decoded, 2);
        }
    }
    if ($path === '/admin/' && is_file($root . '/admin/index.php')) {
        require $root . '/admin/index.php';
        return true;
    }
    if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
        return true;
    }
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
