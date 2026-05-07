<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Repositories\DeviceRepository;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use DateTimeImmutable;

final class ScanController
{
    public function show(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $device = (new DeviceRepository())->findByToken($token);
        if (!$device) {
            http_response_code(404);
            view('errors/404', ['title' => 'QR gecersiz']);
            return;
        }

        view('scan/show', [
            'title' => 'Cihaz Dogrulama',
            'device' => $device,
            'token' => $token,
        ]);
    }

    public function requestLink(): void
    {
        verify_csrf();

        $token = trim((string) ($_POST['token'] ?? ''));
        $device = (new DeviceRepository())->findByToken($token);
        if (!$device) {
            flash('QR kod gecersiz.', 'error');
            redirect('/scan?token=' . urlencode($token));
        }

        $emails = DeviceRepository::emailsFromDevice($device);
        if ($emails === []) {
            flash('Bu cihaz icin yetkili mail adresi tanimli degil.', 'error');
            redirect('/scan?token=' . urlencode($token));
        }

        $accessToken = $this->newAccessToken();
        $accessUrl = url('/device/' . (int) $device['id'] . '/details?access=' . urlencode($accessToken));
        foreach ($emails as $email) {
            Database::pdo()->prepare('INSERT INTO access_codes (device_id, email, code_hash, expires_at, created_at) VALUES (:device_id, :email, :code_hash, DATE_ADD(NOW(), INTERVAL 48 HOUR), :created_at)')
                ->execute([
                    'device_id' => $device['id'],
                    'email' => $email,
                    'code_hash' => password_hash($accessToken, PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
        }

        $html = '<p><strong>' . e($device['code']) . '</strong> kodlu cihaz detaylarini gormek icin asagidaki baglantiyi kullanin.</p>'
            . '<p><a href="' . e($accessUrl) . '" style="display:inline-block;padding:10px 14px;background:#0f766e;color:#fff;text-decoration:none;border-radius:6px;">Cihaz Detaylarini Ac</a></p>'
            . '<p>Baglanti 48 saat gecerlidir. QR kod sabit kalir; her talepte yeni bir giris baglantisi uretilir.</p>'
            . '<p style="word-break:break-all;"><small>' . e($accessUrl) . '</small></p>';

        (new NotificationService())->mail($emails, $device['code'] . ' cihaz giris linki', $html, 'scan.access_link', (int) $device['id'], null, $accessToken);
        AuditLogger::log('scan.link_requested', $device['code']);
        flash('48 saatlik giris linki tanimli mail adreslerine gonderildi.');
        redirect('/scan?token=' . urlencode($token));
    }

    public function details(string $id): void
    {
        $deviceId = (int) $id;
        $device = (new DeviceRepository())->find($deviceId);
        if (!$device) {
            http_response_code(404);
            view('errors/404', ['title' => 'Cihaz bulunamadi']);
            return;
        }

        $accessToken = trim((string) ($_GET['access'] ?? ''));
        if ($accessToken !== '') {
            $validUntil = $this->validateAccessLink($deviceId, $accessToken);
            if ($validUntil > time()) {
                $_SESSION['allowed_devices'][$deviceId] = $validUntil;
                AuditLogger::log('scan.link_verified', $device['code']);
                redirect('/device/' . $deviceId . '/details');
            }

            flash('Giris linki hatali veya 48 saatlik suresi dolmus.', 'error');
            redirect('/scan?token=' . urlencode((string) $device['qr_token']));
        }

        $allowedUntil = (int) ($_SESSION['allowed_devices'][$deviceId] ?? 0);
        if ($allowedUntil < time()) {
            flash('Cihaz detaylari icin QR kodu okutun ve yetkili mail adresine gelen giris linkini acin.', 'error');
            redirect('/scan?token=' . urlencode((string) $device['qr_token']));
        }

        view('scan/details', [
            'title' => $device['code'],
            'device' => $device,
            'allowedUntil' => $allowedUntil,
        ]);
    }

    private function newAccessToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function validateAccessLink(int $deviceId, string $token): int
    {
        $statement = Database::pdo()->prepare('SELECT * FROM access_codes WHERE device_id = :device_id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 100');
        $statement->execute(['device_id' => $deviceId]);

        foreach ($statement->fetchAll() as $accessCode) {
            if (!password_verify($token, (string) $accessCode['code_hash'])) {
                continue;
            }

            if (empty($accessCode['used_at'])) {
                Database::pdo()->prepare('UPDATE access_codes SET used_at = :used_at WHERE id = :id')
                    ->execute(['used_at' => date('Y-m-d H:i:s'), 'id' => $accessCode['id']]);
            }

            return (new DateTimeImmutable((string) $accessCode['expires_at']))->getTimestamp();
        }

        return 0;
    }
}
