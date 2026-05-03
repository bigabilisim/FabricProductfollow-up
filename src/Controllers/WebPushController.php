<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditLogger;
use App\Services\WebPushService;
use Throwable;

final class WebPushController
{
    public function publicKey(): void
    {
        require_auth();
        $service = new WebPushService();

        try {
            $user = current_user();
            $this->json([
                'ok' => true,
                'available' => $service->isAvailable(),
                'publicKey' => $service->publicKey(),
                'subscriptionCount' => $service->subscriptionCount(isset($user['id']) ? (int) $user['id'] : null),
            ]);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function subscribe(): void
    {
        require_auth();
        verify_csrf();

        try {
            $payload = $this->jsonInput();
            (new WebPushService())->saveSubscription($payload, current_user());
            AuditLogger::log('webpush.subscribed');
            $this->json(['ok' => true, 'message' => 'Web Push aboneligi aktif.']);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function unsubscribe(): void
    {
        require_auth();
        verify_csrf();

        try {
            $payload = $this->jsonInput();
            (new WebPushService())->unsubscribe((string) ($payload['endpoint'] ?? ''));
            AuditLogger::log('webpush.unsubscribed');
            $this->json(['ok' => true, 'message' => 'Web Push aboneligi kapatildi.']);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function test(): void
    {
        require_auth();
        verify_csrf();

        try {
            $user = current_user();
            $sent = (new WebPushService())->sendToUser((int) ($user['id'] ?? 0), 'Test bildirimi', 'Web Push bildirimleri calisiyor.', '/admin/settings');
            AuditLogger::log('webpush.test', 'sent=' . $sent);
            $this->json([
                'ok' => $sent > 0,
                'message' => $sent > 0 ? 'Test bildirimi gonderildi.' : 'Aktif Web Push aboneligi bulunamadi.',
            ], $sent > 0 ? 200 : 422);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function jsonInput(): array
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        return is_array($payload) ? $payload : [];
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
