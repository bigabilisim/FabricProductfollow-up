<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = Config::load();
        $host = (string) $config->get('database.host', '127.0.0.1');
        $port = (int) $config->get('database.port', 3306);
        $name = (string) $config->get('database.name', '');
        $charset = (string) $config->get('database.charset', 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        self::$pdo = new PDO($dsn, (string) $config->get('database.user', ''), (string) $config->get('database.password', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    public static function connectWithoutDatabase(array $database): PDO
    {
        $host = (string) ($database['host'] ?? '127.0.0.1');
        $port = (int) ($database['port'] ?? 3306);
        $charset = (string) ($database['charset'] ?? 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";

        return new PDO($dsn, (string) ($database['user'] ?? ''), (string) ($database['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}

