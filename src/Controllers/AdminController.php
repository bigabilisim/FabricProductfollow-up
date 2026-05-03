<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\DeviceRepository;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\WebPushService;
use Throwable;

final class AdminController
{
    public function dashboard(): void
    {
        require_auth();

        $devices = new DeviceRepository();
        $pdo = Database::pdo();
        $pending = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_events WHERE status = 'pending'")->fetchColumn();
        $noResponse = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_events WHERE status IN ('not_done', 'no_response')")->fetchColumn();

        view('admin/dashboard', [
            'title' => 'Admin Panel',
            'deviceCount' => $devices->count(),
            'deletedDeviceCount' => $devices->countDeleted(),
            'dueSoonCount' => $devices->countDueSoon(30),
            'pendingCount' => $pending,
            'riskCount' => $noResponse,
            'latestDevices' => $devices->latest(),
            'dueSoon' => $devices->dueSoon(30),
        ]);
    }

    public function backups(): void
    {
        require_auth();

        view('admin/backups', [
            'title' => 'Yedekler',
            'backups' => (new BackupService())->latest(),
        ]);
    }

    public function createBackup(): void
    {
        require_auth();
        verify_csrf();

        try {
            $backup = (new BackupService())->create();
            AuditLogger::log('backup.created', $backup['file_name']);
            flash('Yedek olusturuldu: ' . $backup['file_name']);
        } catch (Throwable $exception) {
            flash('Yedek olusturulamadi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/backups');
    }

    public function mailBackup(): void
    {
        require_auth();
        verify_csrf();

        try {
            (new BackupService())->mailBackup((int) ($_POST['backup_id'] ?? 0));
            AuditLogger::log('backup.mailed', 'backup_id=' . (int) ($_POST['backup_id'] ?? 0));
            flash('Yedek mail olarak gonderildi.');
        } catch (Throwable $exception) {
            flash('Yedek maili gonderilemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/backups');
    }

    public function settings(): void
    {
        require_auth();

        view('admin/settings', [
            'title' => 'Ayarlar',
            'app' => Config::load()->get('app', []),
            'mail' => Config::load()->get('mail', []),
            'webPushAvailable' => (new WebPushService())->isAvailable(),
            'webPushSubscriptionCount' => $this->webPushSubscriptionCount(),
        ]);
    }

    public function updateSettings(): void
    {
        require_auth();
        verify_csrf();

        try {
            $config = Config::load()->all();
            $currentMail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
            $password = (string) ($_POST['smtp_password'] ?? '');

            $driver = (string) ($_POST['mail_driver'] ?? 'smtp');
            $encryption = (string) ($_POST['smtp_encryption'] ?? 'ssl');

            $config['app'] = is_array($config['app'] ?? null) ? $config['app'] : [];
            $config['app']['logo_path'] = $this->logoPathFromRequest((string) ($config['app']['logo_path'] ?? ''));

            $config['mail'] = [
                'driver' => in_array($driver, ['mail', 'smtp'], true) ? $driver : 'smtp',
                'from_email' => trim((string) ($_POST['from_email'] ?? '')),
                'from_name' => trim((string) ($_POST['from_name'] ?? 'Fabrika QR Bakim Takip')),
                'smtp_host' => trim((string) ($_POST['smtp_host'] ?? '')),
                'smtp_port' => (int) ($_POST['smtp_port'] ?? 465),
                'smtp_user' => trim((string) ($_POST['smtp_user'] ?? '')),
                'smtp_password' => $password !== '' ? $password : (string) ($currentMail['smtp_password'] ?? ''),
                'smtp_encryption' => in_array($encryption, ['ssl', 'tls', 'none'], true) ? $encryption : 'ssl',
                'backup_recipients' => $this->csvEmails((string) ($_POST['backup_recipients'] ?? '')),
            ];

            Config::write($config);
            AuditLogger::log('settings.updated', 'Ayarlar guncellendi.');
            flash('Ayarlar kaydedildi.');
        } catch (Throwable $exception) {
            flash('Ayarlar kaydedilemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/settings');
    }

    private function logoPathFromRequest(string $currentLogoPath): string
    {
        if (!empty($_POST['remove_logo'])) {
            $this->deleteManagedLogo($currentLogoPath);
            return '';
        }

        $upload = $_FILES['site_logo'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $currentLogoPath;
        }

        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Logo yuklenemedi.');
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Logo dosyasi okunamadi.');
        }

        if ((int) ($upload['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Logo dosyasi 2 MB boyutunu gecmemeli.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: '';
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new \RuntimeException('Logo PNG, JPG veya WebP formatinda olmali.');
        }

        $imageSize = getimagesize($tmpName);
        if ($imageSize === false) {
            throw new \RuntimeException('Logo gorseli dogrulanamadi.');
        }

        $directory = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Logo klasoru olusturulamadi.');
        }

        $fileName = 'logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
        $target = $directory . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $target)) {
            throw new \RuntimeException('Logo dosyasi kaydedilemedi.');
        }

        $this->deleteManagedLogo($currentLogoPath);
        return '/uploads/' . $fileName;
    }

    private function deleteManagedLogo(string $logoPath): void
    {
        if ($logoPath === '' || !str_starts_with($logoPath, '/uploads/logo-')) {
            return;
        }

        $file = dirname(__DIR__, 2) . '/public' . $logoPath;
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function csvEmails(string $csv): array
    {
        $emails = preg_split('/[\s,;]+/', $csv) ?: [];
        return array_values(array_unique(array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    private function webPushSubscriptionCount(): int
    {
        try {
            $user = current_user();
            return (new WebPushService())->subscriptionCount(isset($user['id']) ? (int) $user['id'] : null);
        } catch (Throwable) {
            return 0;
        }
    }
}
