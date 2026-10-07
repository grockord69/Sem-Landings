<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (cfg('db.ssl_ca')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = cfg('db.ssl_ca');
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        cfg('db.host'),
        (int) cfg('db.port', 3306),
        cfg('db.name')
    );

    $pdo = new PDO($dsn, (string) cfg('db.user'), (string) cfg('db.password'), $options);
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
