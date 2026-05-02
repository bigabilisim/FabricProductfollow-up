<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DeviceRepository;
use App\Services\AuditLogger;
use App\Services\QrCode;
use Throwable;

final class DeviceController
{
    private DeviceRepository $devices;

    public function __construct()
    {
        require_auth();
        $this->devices = new DeviceRepository();
    }

    public function index(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        view('admin/devices/index', [
            'title' => 'Cihazlar',
            'devices' => $this->devices->all($search ?: null),
            'search' => $search,
            'deletedCount' => $this->devices->countDeleted(),
        ]);
    }

    public function deleted(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        view('admin/devices/deleted', [
            'title' => 'Silinen Cihazlar',
            'devices' => $this->devices->deleted($search ?: null),
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $identity = $this->devices->suggestIdentity('TR', (int) date('Y'));
        view('admin/devices/form', [
            'title' => 'Yeni Cihaz',
            'device' => null,
            'action' => '/admin/devices',
            'identity' => $identity,
        ]);
    }

    public function store(): void
    {
        verify_csrf();

        try {
            $id = $this->devices->create($_POST);
            AuditLogger::log('device.created', 'device_id=' . $id);
            flash('Cihaz kaydedildi.');
            redirect('/admin/devices/' . $id);
        } catch (Throwable $exception) {
            flash('Cihaz kaydedilemedi: ' . $exception->getMessage(), 'error');
            redirect('/admin/devices/create');
        }
    }

    public function show(string $id): void
    {
        $device = $this->findOrFail((int) $id);
        view('admin/devices/show', [
            'title' => $device['code'],
            'device' => $device,
        ]);
    }

    public function edit(string $id): void
    {
        $device = $this->findOrFail((int) $id);
        $identity = $this->devices->suggestIdentity((string) $device['country_code'], (int) $device['production_year'], (int) $device['id']);
        view('admin/devices/form', [
            'title' => 'Cihaz Duzenle',
            'device' => $device,
            'action' => '/admin/devices/' . $device['id'],
            'identity' => $identity,
        ]);
    }

    public function update(string $id): void
    {
        verify_csrf();
        $deviceId = (int) $id;

        try {
            $this->devices->update($deviceId, $_POST);
            AuditLogger::log('device.updated', 'device_id=' . $deviceId);
            flash('Cihaz guncellendi.');
            redirect('/admin/devices/' . $deviceId);
        } catch (Throwable $exception) {
            flash('Cihaz guncellenemedi: ' . $exception->getMessage(), 'error');
            redirect('/admin/devices/' . $deviceId . '/edit');
        }
    }

    public function delete(string $id): void
    {
        verify_csrf();
        $this->devices->delete((int) $id);
        AuditLogger::log('device.deleted', 'device_id=' . (int) $id);
        flash('Cihaz silinenler havuzuna tasindi.');
        redirect('/admin/devices');
    }

    public function restore(string $id): void
    {
        verify_csrf();
        $this->devices->restore((int) $id);
        AuditLogger::log('device.restored', 'device_id=' . (int) $id);
        flash('Cihaz geri alindi.');
        redirect('/admin/devices/deleted');
    }

    public function purge(string $id): void
    {
        verify_csrf();
        $this->devices->purge((int) $id);
        AuditLogger::log('device.purged', 'device_id=' . (int) $id);
        flash('Cihaz kalici olarak silindi.');
        redirect('/admin/devices/deleted');
    }

    public function nextCode(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $countryCode = (string) ($_GET['country_code'] ?? 'TR');
        $year = (int) ($_GET['production_year'] ?? date('Y'));
        $deviceId = isset($_GET['device_id']) && $_GET['device_id'] !== '' ? (int) $_GET['device_id'] : null;

        echo json_encode([
            'ok' => true,
            'identity' => $this->devices->suggestIdentity($countryCode, $year, $deviceId),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function qrSvg(string $id): void
    {
        $device = $this->findOrFail((int) $id);
        $targetUrl = url('/scan?token=' . urlencode((string) $device['qr_token']));
        $svg = (new QrCode())->svg($targetUrl, 8, 4);

        header('Content-Type: image/svg+xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $device['code'] . '.svg"');
        echo $svg;
    }

    public function label(string $id): void
    {
        $device = $this->findOrFail((int) $id);
        view('admin/devices/label', [
            'title' => 'Etiket',
            'device' => $device,
            'qrDataUri' => (new QrCode())->dataUri(url('/scan?token=' . urlencode((string) $device['qr_token']))),
        ]);
    }

    private function findOrFail(int $id): array
    {
        $device = $this->devices->find($id);
        if (!$device) {
            http_response_code(404);
            view('errors/404', ['title' => 'Cihaz bulunamadi']);
            exit;
        }

        return $device;
    }
}
