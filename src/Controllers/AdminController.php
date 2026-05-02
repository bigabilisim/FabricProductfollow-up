<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\DeviceRepository;
use App\Services\AuditLogger;
use App\Services\BackupService;
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
            'mail' => Config::load()->get('mail', []),
        ]);
    }

    public function updateSettings(): void
    {
        require_auth();
        verify_csrf();

        $config = Config::load()->all();
        $currentMail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
        $password = (string) ($_POST['smtp_password'] ?? '');

        $driver = (string) ($_POST['mail_driver'] ?? 'smtp');
        $encryption = (string) ($_POST['smtp_encryption'] ?? 'ssl');

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

        try {
            Config::write($config);
            AuditLogger::log('settings.mail.updated', 'Mail ayarlari guncellendi.');
            flash('Mail ayarlari kaydedildi.');
        } catch (Throwable $exception) {
            flash('Mail ayarlari kaydedilemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/settings');
    }

    private function csvEmails(string $csv): array
    {
        $emails = preg_split('/[\s,;]+/', $csv) ?: [];
        return array_values(array_unique(array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }
}
