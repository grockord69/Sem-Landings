<?php
declare(strict_types=1);
namespace Innova;
function db(): \PDO {
    static $pdo;
    if ($pdo instanceof \PDO) { return $pdo; }
    $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_EMULATE_PREPARES => false];
    if (cfg('db.ssl_ca')) { $options[\PDO::MYSQL_ATTR_SSL_CA] = cfg('db.ssl_ca'); $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true; }
    $pdo = new \PDO('mysql:host=' . cfg('db.host') . ';port=' . (int)cfg('db.port') . ';dbname=' . cfg('db.name') . ';charset=utf8mb4', (string)cfg('db.user'), (string)cfg('db.password'), $options);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
