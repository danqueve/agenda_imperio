<?php
declare(strict_types=1);

/**
 * Conexión PDO (singleton) hacia la base propia c2881399_agenda.
 * En WAMP local: root sin password. Para producción, duplicar este
 * archivo como config/database.php en el servidor y cambiar
 * DB_USER/DB_PASS (ver config/database.prod.php.example en Fase 4).
 */

const DB_HOST    = 'localhost';
const DB_NAME    = 'c2881399_agenda';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$pdo;
    }
}
