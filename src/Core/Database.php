<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Fábrica de la conexión PDO (MariaDB/MySQL). Sentencias preparadas nativas y excepciones. */
final class Database
{
    public static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Config::get('db.host', '127.0.0.1'),
            (int) Config::get('db.port', 3306),
            Config::get('db.name', 'soporte_ti')
        );
        $pdo = new PDO($dsn, (string) Config::get('db.user', 'root'), (string) Config::get('db.password', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // La sesión trabaja en la misma hora local que la aplicación (las fechas son DATETIME locales).
        $tz     = new DateTimeZone((string) Config::get('app.timezone', 'UTC'));
        $offset = (new DateTimeImmutable('now', $tz))->format('P'); // p. ej. -05:00
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
        return $pdo;
    }
}
