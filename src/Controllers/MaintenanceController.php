<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MaintenanceService;
use Throwable;

final class MaintenanceController
{
    public function respond(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $service = new MaintenanceService();
        $event = $service->eventByToken($token);
        if (!$event) {
            http_response_code(404);
            view('errors/404', ['title' => 'Bakim linki bulunamadi']);
            return;
        }

        view('maintenance/respond', [
            'title' => 'Bakim Cevabi',
            'event' => $event,
            'device' => $service->device((int) $event['device_id']),
            'token' => $token,
            'status' => $status,
        ]);
    }

    public function storeResponse(): void
    {
        verify_csrf();

        try {
            $device = (new MaintenanceService())->respond(
                trim((string) ($_POST['token'] ?? '')),
                trim((string) ($_POST['status'] ?? '')),
                trim((string) ($_POST['rescheduled_at'] ?? '')) ?: null
            );
            flash($device['code'] . ' icin bakim cevabi kaydedildi.');
            redirect('/maintenance/respond?token=' . urlencode((string) ($_POST['token'] ?? '')) . '&status=' . urlencode((string) ($_POST['status'] ?? '')));
        } catch (Throwable $exception) {
            flash('Bakim cevabi kaydedilemedi: ' . $exception->getMessage(), 'error');
            redirect('/maintenance/respond?token=' . urlencode((string) ($_POST['token'] ?? '')));
        }
    }

    public function readAck(): void
    {
        $event = (new MaintenanceService())->acknowledgeRead(trim((string) ($_GET['token'] ?? '')));
        view('maintenance/read_ack', [
            'title' => 'Okundu Onayi',
            'event' => $event,
        ]);
    }
}

