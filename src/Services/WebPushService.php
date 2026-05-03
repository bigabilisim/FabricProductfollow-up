<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use PDO;
use RuntimeException;
use Throwable;

final class WebPushService
{
    private static bool $schemaChecked = false;

    public function isAvailable(): bool
    {
        return class_exists(WebPush::class) && class_exists(Subscription::class) && class_exists(VAPID::class);
    }

    public function publicKey(): string
    {
        $this->ensureConfigured();
        return (string) Config::load()->get('webpush.public_key', '');
    }

    public function subscriptionCount(?int $userId = null): int
    {
        $this->ensureSchema();

        if ($userId !== null) {
            $statement = Database::pdo()->prepare('SELECT COUNT(*) FROM web_push_subscriptions WHERE user_id = :user_id AND active = 1');
            $statement->execute(['user_id' => $userId]);
            return (int) $statement->fetchColumn();
        }

        return (int) Database::pdo()->query('SELECT COUNT(*) FROM web_push_subscriptions WHERE active = 1')->fetchColumn();
    }

    public function saveSubscription(array $data, ?array $user = null): void
    {
        $this->ensureConfigured();
        $this->ensureSchema();

        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
        $p256dh = trim((string) ($keys['p256dh'] ?? ''));
        $auth = trim((string) ($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new RuntimeException('Bildirim aboneligi eksik geldi.');
        }

        $hash = hash('sha256', $endpoint);
        $encoding = trim((string) ($data['contentEncoding'] ?? 'aes128gcm')) ?: 'aes128gcm';

        Database::pdo()->prepare('
            INSERT INTO web_push_subscriptions (
                user_id, endpoint, endpoint_hash, p256dh, auth_token, content_encoding,
                user_agent, active, last_error, created_at, updated_at
            ) VALUES (
                :user_id, :endpoint, :endpoint_hash, :p256dh, :auth_token, :content_encoding,
                :user_agent, 1, NULL, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                endpoint = VALUES(endpoint),
                p256dh = VALUES(p256dh),
                auth_token = VALUES(auth_token),
                content_encoding = VALUES(content_encoding),
                user_agent = VALUES(user_agent),
                active = 1,
                last_error = NULL,
                updated_at = VALUES(updated_at)
        ')->execute([
            'user_id' => isset($user['id']) ? (int) $user['id'] : null,
            'endpoint' => $endpoint,
            'endpoint_hash' => $hash,
            'p256dh' => $p256dh,
            'auth_token' => $auth,
            'content_encoding' => in_array($encoding, ['aes128gcm', 'aesgcm'], true) ? $encoding : 'aes128gcm',
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unsubscribe(string $endpoint): void
    {
        $this->ensureSchema();
        if ($endpoint === '') {
            return;
        }

        Database::pdo()->prepare('UPDATE web_push_subscriptions SET active = 0, updated_at = :updated_at WHERE endpoint_hash = :hash')
            ->execute(['updated_at' => date('Y-m-d H:i:s'), 'hash' => hash('sha256', $endpoint)]);
    }

    public function sendToAll(string $title, string $body, string $url = '/admin'): int
    {
        return $this->send($this->activeSubscriptions(), $title, $body, $url);
    }

    public function sendToUser(int $userId, string $title, string $body, string $url = '/admin'): int
    {
        $this->ensureSchema();
        $statement = Database::pdo()->prepare('SELECT * FROM web_push_subscriptions WHERE user_id = :user_id AND active = 1');
        $statement->execute(['user_id' => $userId]);

        return $this->send($statement->fetchAll(), $title, $body, $url);
    }

    private function send(array $subscriptions, string $title, string $body, string $url): int
    {
        if (!$subscriptions) {
            return 0;
        }

        $this->ensureConfigured();
        $config = Config::load();
        if (!$config->get('webpush.enabled', true)) {
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) $config->get('webpush.subject', 'mailto:bigaofis@alarmbigabilisim.com'),
                'publicKey' => (string) $config->get('webpush.public_key', ''),
                'privateKey' => (string) $config->get('webpush.private_key', ''),
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => app_path($url),
            'icon' => app_path((string) $config->get('app.logo_path', '/assets/pwa-icon.svg')),
            'badge' => app_path('/assets/pwa-icon.svg'),
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        foreach ($subscriptions as $row) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => (string) $row['endpoint'],
                    'publicKey' => (string) $row['p256dh'],
                    'authToken' => (string) $row['auth_token'],
                    'contentEncoding' => (string) ($row['content_encoding'] ?: 'aes128gcm'),
                ]);

                $report = $webPush->sendOneNotification($subscription, $payload);
                if ($report->isSuccess()) {
                    $sent++;
                    $this->markHealthy((int) $row['id']);
                    continue;
                }

                $this->markFailed((int) $row['id'], $report->getReason(), $report->isSubscriptionExpired());
            } catch (Throwable $exception) {
                $this->markFailed((int) $row['id'], $exception->getMessage(), false);
            }
        }

        return $sent;
    }

    private function activeSubscriptions(): array
    {
        $this->ensureSchema();
        return Database::pdo()->query('SELECT * FROM web_push_subscriptions WHERE active = 1')->fetchAll();
    }

    private function markHealthy(int $id): void
    {
        Database::pdo()->prepare('UPDATE web_push_subscriptions SET last_error = NULL, updated_at = :updated_at WHERE id = :id')
            ->execute(['id' => $id, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function markFailed(int $id, string $error, bool $expired): void
    {
        Database::pdo()->prepare('UPDATE web_push_subscriptions SET active = :active, last_error = :last_error, updated_at = :updated_at WHERE id = :id')
            ->execute([
                'id' => $id,
                'active' => $expired ? 0 : 1,
                'last_error' => substr($error, 0, 1000),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function ensureConfigured(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Web Push kutuphanesi yuklu degil. Composer install calistirin.');
        }

        $config = Config::load()->all();
        $webPush = is_array($config['webpush'] ?? null) ? $config['webpush'] : [];
        if (!empty($webPush['public_key']) && !empty($webPush['private_key'])) {
            return;
        }

        $keys = VAPID::createVapidKeys();
        $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
        $fromEmail = (string) ($mail['from_email'] ?? 'bigaofis@alarmbigabilisim.com');

        $config['webpush'] = array_merge($webPush, [
            'enabled' => (bool) ($webPush['enabled'] ?? true),
            'public_key' => $keys['publicKey'],
            'private_key' => $keys['privateKey'],
            'subject' => (string) ($webPush['subject'] ?? 'mailto:' . $fromEmail),
        ]);

        Config::write($config);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        $pdo = Database::pdo();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS web_push_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                endpoint TEXT NOT NULL,
                endpoint_hash CHAR(64) NOT NULL UNIQUE,
                p256dh VARCHAR(255) NOT NULL,
                auth_token VARCHAR(255) NOT NULL,
                content_encoding VARCHAR(40) NOT NULL DEFAULT 'aes128gcm',
                user_agent VARCHAR(255) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_error TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_web_push_user_active (user_id, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$schemaChecked = true;
    }
}
