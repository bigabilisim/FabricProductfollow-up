<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Repositories\DeviceRepository;
use App\Services\AuditLogger;
use App\Services\NotificationService;

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

    public function requestCode(): void
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

        $code = (string) random_int(100000, 999999);
        foreach ($emails as $email) {
            Database::pdo()->prepare('INSERT INTO access_codes (device_id, email, code_hash, expires_at, created_at) VALUES (:device_id, :email, :code_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE), :created_at)')
                ->execute([
                    'device_id' => $device['id'],
                    'email' => $email,
                    'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
        }

        $html = '<p><strong>' . e($device['code']) . '</strong> kodlu cihaz detaylarini gormek icin dogrulama kodunuz:</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">' . e($code) . '</p>'
            . '<p>Kod 10 dakika gecerlidir.</p>';

        (new NotificationService())->mail($emails, $device['code'] . ' dogrulama kodu', $html, 'scan.access_code', (int) $device['id'], null, $code);
        AuditLogger::log('scan.code_requested', $device['code']);
        flash('Dogrulama kodu tanimli mail adreslerine gonderildi.');
        redirect('/scan?token=' . urlencode($token));
    }

    public function verifyCode(): void
    {
        verify_csrf();

        $token = trim((string) ($_POST['token'] ?? ''));
        $code = trim((string) ($_POST['code'] ?? ''));
        $device = (new DeviceRepository())->findByToken($token);
        if (!$device) {
            flash('QR kod gecersiz.', 'error');
            redirect('/scan?token=' . urlencode($token));
        }

        $statement = Database::pdo()->prepare('SELECT * FROM access_codes WHERE device_id = :device_id AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC LIMIT 20');
        $statement->execute(['device_id' => $device['id']]);
        foreach ($statement->fetchAll() as $accessCode) {
            if (password_verify($code, (string) $accessCode['code_hash'])) {
                Database::pdo()->prepare('UPDATE access_codes SET used_at = :used_at WHERE id = :id')
                    ->execute(['used_at' => date('Y-m-d H:i:s'), 'id' => $accessCode['id']]);
                $_SESSION['allowed_devices'][(int) $device['id']] = time() + 3600;
                AuditLogger::log('scan.verified', $device['code']);
                redirect('/device/' . $device['id'] . '/details');
            }
        }

        flash('Dogrulama kodu hatali veya suresi dolmus.', 'error');
        redirect('/scan?token=' . urlencode($token));
    }

    public function details(string $id): void
    {
        $deviceId = (int) $id;
        $allowedUntil = $_SESSION['allowed_devices'][$deviceId] ?? 0;
        if ($allowedUntil < time()) {
            flash('Cihaz detaylari icin once QR kod dogrulamasi yapin.', 'error');
            redirect('/');
        }

        $device = (new DeviceRepository())->find($deviceId);
        if (!$device) {
            http_response_code(404);
            view('errors/404', ['title' => 'Cihaz bulunamadi']);
            return;
        }

        view('scan/details', [
            'title' => $device['code'],
            'device' => $device,
        ]);
    }
}

