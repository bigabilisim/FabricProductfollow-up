<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;

final class Installer
{
    public function install(array $post, ?array $uploadedBackup = null): void
    {
        $config = $this->configFromPost($post);
        $pdo = Database::connectWithoutDatabase($config['database']);
        $database = $this->quoteIdentifier($config['database']['name']);
        $charset = preg_replace('/[^a-zA-Z0-9_]/', '', $config['database']['charset']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$database} CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
        $pdo->exec("USE {$database}");

        $mode = (string) ($post['install_mode'] ?? 'fresh');
        if ($mode === 'restore' && $uploadedBackup && ($uploadedBackup['tmp_name'] ?? '') !== '') {
            $this->importSql($pdo, (string) file_get_contents($uploadedBackup['tmp_name']));
        } else {
            $this->importSql($pdo, (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
            $this->createAdmin($pdo, $post);
        }

        $config['app']['installed'] = true;
        Config::write($config);
        Database::reset();
        AuditLogger::log('install.completed', 'Kurulum sihirbazi tamamlandi.', (string) ($post['admin_email'] ?? null));
    }

    private function configFromPost(array $post): array
    {
        $defaults = Config::load();
        $warningDays = $this->csvNumbers((string) ($post['maintenance_warning_days'] ?? '30,14,7,3,1'));
        $defaultBackupRecipients = $defaults->get('mail.backup_recipients', []);
        $backupRecipients = $this->csvEmails((string) ($post['backup_recipients'] ?? (is_array($defaultBackupRecipients) ? implode(',', $defaultBackupRecipients) : '')));

        $basePath = '/' . trim((string) ($post['base_path'] ?? ''), '/');
        if ($basePath === '/') {
            $basePath = '';
        }

        return [
            'app' => [
                'installed' => false,
                'site_name' => trim((string) ($post['site_name'] ?? 'Fabrika QR Bakim Takip')),
                'version' => '1.0V',
                'base_url' => rtrim(trim((string) ($post['base_url'] ?? '')), '/'),
                'base_path' => $basePath,
                'timezone' => trim((string) ($post['timezone'] ?? 'Europe/Istanbul')),
                'maintenance_warning_days' => $warningDays,
            ],
            'database' => [
                'host' => trim((string) ($post['db_host'] ?? '127.0.0.1')),
                'port' => (int) ($post['db_port'] ?? 3306),
                'name' => trim((string) ($post['db_name'] ?? 'factory_qr')),
                'user' => trim((string) ($post['db_user'] ?? 'root')),
                'password' => (string) ($post['db_password'] ?? ''),
                'charset' => 'utf8mb4',
            ],
            'mail' => [
                'driver' => (string) ($post['mail_driver'] ?? $defaults->get('mail.driver', 'mail')),
                'from_email' => trim((string) ($post['from_email'] ?? $defaults->get('mail.from_email', 'noreply@example.com'))),
                'from_name' => trim((string) ($post['from_name'] ?? $defaults->get('mail.from_name', 'Fabrika QR Bakim Takip'))),
                'smtp_host' => trim((string) ($post['smtp_host'] ?? $defaults->get('mail.smtp_host', ''))),
                'smtp_port' => (int) ($post['smtp_port'] ?? $defaults->get('mail.smtp_port', 587)),
                'smtp_user' => trim((string) ($post['smtp_user'] ?? $defaults->get('mail.smtp_user', ''))),
                'smtp_password' => (string) ($post['smtp_password'] ?? $defaults->get('mail.smtp_password', '')),
                'smtp_encryption' => (string) ($post['smtp_encryption'] ?? $defaults->get('mail.smtp_encryption', 'tls')),
                'backup_recipients' => $backupRecipients,
            ],
            'telegram' => [
                'enabled' => !empty($post['telegram_enabled']),
                'bot_token' => trim((string) ($post['telegram_bot_token'] ?? '')),
                'chat_id' => trim((string) ($post['telegram_chat_id'] ?? '')),
            ],
            'whatsapp' => [
                'enabled' => !empty($post['whatsapp_enabled']),
                'webhook_url' => trim((string) ($post['whatsapp_webhook_url'] ?? '')),
                'token' => trim((string) ($post['whatsapp_token'] ?? '')),
            ],
        ];
    }

    private function createAdmin(PDO $pdo, array $post): void
    {
        $email = trim((string) ($post['admin_email'] ?? ''));
        $password = (string) ($post['admin_password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('Admin e-posta gecersiz veya parola 8 karakterden kisa.');
        }

        $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (:name, :email, :password_hash, :role, :created_at)');
        $statement->execute([
            'name' => trim((string) ($post['admin_name'] ?? 'Admin')),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function importSql(PDO $pdo, string $sql): void
    {
        foreach ($this->splitSql($sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    private function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = $quote === $char ? null : ($quote ?? $char);
            }

            if ($char === ';' && $quote === null) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function quoteIdentifier(string $value): string
    {
        if ($value === '') {
            throw new RuntimeException('Veritabani adi bos olamaz.');
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    private function csvNumbers(string $csv): array
    {
        $numbers = array_map('intval', preg_split('/[\s,;]+/', $csv) ?: []);
        $numbers = array_values(array_unique(array_filter($numbers, static fn (int $day): bool => $day > 0)));
        rsort($numbers);

        return $numbers ?: [30, 14, 7, 3, 1];
    }

    private function csvEmails(string $csv): array
    {
        $emails = preg_split('/[\s,;]+/', $csv) ?: [];
        return array_values(array_unique(array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }
}
