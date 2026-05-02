<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;

final class BackupService
{
    public function create(): array
    {
        $pdo = Database::pdo();
        $databaseName = (string) Config::load()->get('database.name', 'factory_qr');
        $timestamp = date('Ymd_His');
        $fileName = $databaseName . '_' . $timestamp . '.sql';
        $path = dirname(__DIR__, 2) . '/storage/backups/' . $fileName;
        $sql = $this->dump($pdo, $databaseName);
        file_put_contents($path, $sql, LOCK_EX);

        $statement = $pdo->prepare('INSERT INTO backups (file_name, file_path, file_size, created_at) VALUES (:file_name, :file_path, :file_size, :created_at)');
        $statement->execute([
            'file_name' => $fileName,
            'file_path' => $path,
            'file_size' => filesize($path) ?: 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'file_name' => $fileName,
            'file_path' => $path,
            'file_size' => filesize($path) ?: 0,
        ];
    }

    public function latest(int $limit = 20): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM backups ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function mailBackup(int $backupId): void
    {
        $statement = Database::pdo()->prepare('SELECT * FROM backups WHERE id = :id');
        $statement->execute(['id' => $backupId]);
        $backup = $statement->fetch();
        if (!$backup || !is_file((string) $backup['file_path'])) {
            throw new RuntimeException('Yedek dosyasi bulunamadi.');
        }

        $recipients = Config::load()->get('mail.backup_recipients', []);
        if (!is_array($recipients) || $recipients === []) {
            throw new RuntimeException('Yedek alicisi tanimli degil.');
        }

        (new Mailer())->send($recipients, 'Gunluk veritabani yedegi', '<p>Veritabani yedegi ekte yer almaktadir.</p>', null, [(string) $backup['file_path']]);
        Database::pdo()->prepare('UPDATE backups SET mailed_at = :mailed_at WHERE id = :id')->execute([
            'mailed_at' => date('Y-m-d H:i:s'),
            'id' => $backupId,
        ]);
    }

    public function createAndMail(): array
    {
        $backup = $this->create();
        $this->mailBackup((int) $backup['id']);

        return $backup;
    }

    private function dump(PDO $pdo, string $databaseName): string
    {
        $lines = [];
        $lines[] = '-- Factory QR backup';
        $lines[] = '-- Created at: ' . date('c');
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = 'CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdentifier($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
        $lines[] = 'USE ' . $this->quoteIdentifier($databaseName) . ';';

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $quoted = $this->quoteIdentifier((string) $table);
            $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch();
            $lines[] = '';
            $lines[] = 'DROP TABLE IF EXISTS ' . $quoted . ';';
            $lines[] = ($create['Create Table'] ?? array_values($create)[1]) . ';';

            $rows = $pdo->query('SELECT * FROM ' . $quoted)->fetchAll();
            foreach ($rows as $row) {
                $columns = array_map(fn (string $column): string => $this->quoteIdentifier($column), array_keys($row));
                $values = array_map(function (mixed $value) use ($pdo): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $value);
                }, array_values($row));

                $lines[] = 'INSERT INTO ' . $quoted . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines) . "\n";
    }

    private function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}

