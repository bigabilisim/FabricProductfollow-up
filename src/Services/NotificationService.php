<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Throwable;

final class NotificationService
{
    public function __construct(private ?Mailer $mailer = null)
    {
        $this->mailer ??= new Mailer();
    }

    public function mail(array|string $recipients, string $subject, string $html, string $type, ?int $deviceId = null, ?int $eventId = null, string $dedupe = ''): void
    {
        $emails = is_array($recipients) ? $recipients : [$recipients];
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $hash = hash('sha256', $type . '|' . $email . '|' . $dedupe);
            if ($this->alreadySent($type, $email, $hash)) {
                continue;
            }

            $this->mailer->send($email, $subject, $html);
            $this->record($deviceId, $eventId, 'mail', $type, $email, $hash);
        }
    }

    public function telegram(string $message): void
    {
        $config = Config::load();
        if (!$config->get('telegram.enabled', false)) {
            return;
        }

        $token = (string) $config->get('telegram.bot_token', '');
        $chatId = (string) $config->get('telegram.chat_id', '');
        if ($token === '' || $chatId === '') {
            return;
        }

        $this->postJson('https://api.telegram.org/bot' . $token . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }

    public function whatsapp(string $message): void
    {
        $config = Config::load();
        if (!$config->get('whatsapp.enabled', false)) {
            return;
        }

        $url = (string) $config->get('whatsapp.webhook_url', '');
        if ($url === '') {
            return;
        }

        $this->postJson($url, [
            'token' => (string) $config->get('whatsapp.token', ''),
            'message' => $message,
        ]);
    }

    private function alreadySent(string $type, string $recipient, string $hash): bool
    {
        try {
            $statement = Database::pdo()->prepare('SELECT COUNT(*) FROM notification_logs WHERE notification_type = :type AND recipient = :recipient AND message_hash = :hash');
            $statement->execute(['type' => $type, 'recipient' => $recipient, 'hash' => $hash]);
            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function record(?int $deviceId, ?int $eventId, string $channel, string $type, string $recipient, string $hash): void
    {
        try {
            Database::pdo()->prepare('INSERT INTO notification_logs (device_id, maintenance_event_id, channel, notification_type, recipient, message_hash, sent_at) VALUES (:device_id, :event_id, :channel, :type, :recipient, :hash, :sent_at)')
                ->execute([
                    'device_id' => $deviceId,
                    'event_id' => $eventId,
                    'channel' => $channel,
                    'type' => $type,
                    'recipient' => $recipient,
                    'hash' => $hash,
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable) {
        }
    }

    private function postJson(string $url, array $payload): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 8,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}

