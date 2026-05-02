<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\DeviceRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class MaintenanceService
{
    public function __construct(private ?NotificationService $notifications = null)
    {
        $this->notifications ??= new NotificationService();
    }

    public function runScheduledNotifications(): array
    {
        return [
            'upcoming' => $this->sendUpcomingWarnings(),
            'due' => $this->sendDueQuestions(),
            'expired' => $this->markExpiredQuestions(),
        ];
    }

    public function respond(string $token, string $status, ?string $rescheduledAt = null): array
    {
        $event = $this->eventByToken($token);
        if (!$event) {
            throw new RuntimeException('Bakim cevabi bulunamadi.');
        }

        if (new DateTimeImmutable((string) $event['response_expires_at']) < new DateTimeImmutable()) {
            throw new RuntimeException('Bu cevap linkinin 48 saatlik suresi dolmus.');
        }

        $allowed = ['done', 'not_done', 'rescheduled'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Gecersiz bakim cevabi.');
        }

        $device = $this->device((int) $event['device_id']);
        $pdo = Database::pdo();

        $pdo->prepare('UPDATE maintenance_events SET status = :status, responded_at = :responded_at, rescheduled_at = :rescheduled_at, updated_at = :updated_at WHERE id = :id')
            ->execute([
                'status' => $status,
                'responded_at' => date('Y-m-d H:i:s'),
                'rescheduled_at' => $status === 'rescheduled' ? $rescheduledAt : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $event['id'],
            ]);

        if ($status === 'done') {
            $next = DeviceRepository::nextMaintenanceDate(date('Y-m-d'), (int) $device['maintenance_period_days']);
            $pdo->prepare('UPDATE devices SET last_maintenance_at = CURDATE(), next_maintenance_at = :next_maintenance_at, updated_at = :updated_at WHERE id = :id')
                ->execute(['next_maintenance_at' => $next, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $device['id']]);
        }

        if ($status === 'rescheduled' && $rescheduledAt) {
            $pdo->prepare('UPDATE devices SET next_maintenance_at = :next_maintenance_at, updated_at = :updated_at WHERE id = :id')
                ->execute(['next_maintenance_at' => $rescheduledAt, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $device['id']]);
        }

        if ($status === 'not_done') {
            $this->sendHazardMail($device, $event);
        }

        AuditLogger::log('maintenance.responded', $device['code'] . ' status=' . $status);

        return $device;
    }

    public function acknowledgeRead(string $token): ?array
    {
        $statement = Database::pdo()->prepare('SELECT e.*, d.code FROM maintenance_events e JOIN devices d ON d.id = e.device_id WHERE e.read_ack_token = :token');
        $statement->execute(['token' => $token]);
        $event = $statement->fetch();
        if (!$event) {
            return null;
        }

        Database::pdo()->prepare('UPDATE maintenance_events SET read_ack_at = :read_ack_at, updated_at = :updated_at WHERE id = :id')
            ->execute(['read_ack_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'id' => $event['id']]);

        return $event;
    }

    public function eventByToken(string $token): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM maintenance_events WHERE response_token = :token');
        $statement->execute(['token' => $token]);
        $event = $statement->fetch();

        return $event ?: null;
    }

    public function device(int $id): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE id = :id');
        $statement->execute(['id' => $id]);
        $device = $statement->fetch();
        if (!$device) {
            throw new RuntimeException('Cihaz bulunamadi.');
        }

        return $device;
    }

    private function sendUpcomingWarnings(): int
    {
        $maxDays = max(Config::load()->get('app.maintenance_warning_days', [30, 14, 7, 3, 1]));
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE next_maintenance_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)');
        $statement->bindValue('days', (int) $maxDays, PDO::PARAM_INT);
        $statement->execute();

        $sent = 0;
        foreach ($statement->fetchAll() as $device) {
            $daysLeft = $this->daysUntil((string) $device['next_maintenance_at']);
            $warningDays = $this->warningDays($device);
            if (!in_array($daysLeft, $warningDays, true)) {
                continue;
            }

            $emails = DeviceRepository::emailsFromDevice($device);
            $subject = $device['code'] . ' bakimi yaklasiyor';
            $html = '<p><strong>' . e($device['code']) . '</strong> kodlu cihaz icin bakim tarihi yaklasiyor.</p>'
                . '<p>Planlanan bakim tarihi: <strong>' . e((string) $device['next_maintenance_at']) . '</strong></p>'
                . '<p>Kalan gun: <strong>' . $daysLeft . '</strong></p>';

            $this->notifications->mail($emails, $subject, $html, 'maintenance.upcoming.' . $daysLeft, (int) $device['id'], null, (string) $device['next_maintenance_at']);
            $this->notifications->telegram($subject . ' - ' . $device['next_maintenance_at']);
            $this->notifications->whatsapp($subject . ' - ' . $device['next_maintenance_at']);
            $sent += count($emails);
        }

        return $sent;
    }

    private function sendDueQuestions(): int
    {
        $devices = Database::pdo()->query("SELECT * FROM devices WHERE next_maintenance_at <= CURDATE()")->fetchAll();
        $sent = 0;

        foreach ($devices as $device) {
            $event = $this->pendingEventForDevice((int) $device['id'], (string) $device['next_maintenance_at']);
            if (!$event) {
                $event = $this->createEvent($device);
            }

            $emails = DeviceRepository::emailsFromDevice($device);
            $html = $this->dueMailHtml($device, $event);
            $subject = $device['code'] . ' bakimi yapildi mi?';
            $this->notifications->mail($emails, $subject, $html, 'maintenance.due', (int) $device['id'], (int) $event['id'], (string) $event['response_token']);
            $this->notifications->telegram($subject);
            $this->notifications->whatsapp($subject);
            $sent += count($emails);
        }

        return $sent;
    }

    private function markExpiredQuestions(): int
    {
        $statement = Database::pdo()->query("SELECT e.*, d.code, d.responsible_emails FROM maintenance_events e JOIN devices d ON d.id = e.device_id WHERE e.status = 'pending' AND e.response_expires_at < NOW()");
        $expired = $statement->fetchAll();

        foreach ($expired as $event) {
            Database::pdo()->prepare("UPDATE maintenance_events SET status = 'no_response', updated_at = :updated_at WHERE id = :id")
                ->execute(['updated_at' => date('Y-m-d H:i:s'), 'id' => $event['id']]);

            $emails = DeviceRepository::emailsFromDevice($event);
            $subject = $event['code'] . ' bakim cevabi alinmadi';
            $html = '<p>48 saatlik cevap suresi doldu. Bakimin durumu icin teknik sorumluya ulasilmasi onerilir.</p>';
            $this->notifications->mail($emails, $subject, $html, 'maintenance.no_response', (int) $event['device_id'], (int) $event['id'], (string) $event['response_token']);
        }

        return count($expired);
    }

    private function pendingEventForDevice(int $deviceId, string $dueAt): ?array
    {
        $statement = Database::pdo()->prepare("SELECT * FROM maintenance_events WHERE device_id = :device_id AND due_at = :due_at AND status = 'pending' LIMIT 1");
        $statement->execute(['device_id' => $deviceId, 'due_at' => $dueAt]);
        $event = $statement->fetch();

        return $event ?: null;
    }

    private function createEvent(array $device): array
    {
        $token = bin2hex(random_bytes(32));
        $statement = Database::pdo()->prepare('INSERT INTO maintenance_events (device_id, due_at, status, response_token, response_expires_at, created_at) VALUES (:device_id, :due_at, :status, :response_token, DATE_ADD(NOW(), INTERVAL 48 HOUR), :created_at)');
        $statement->execute([
            'device_id' => $device['id'],
            'due_at' => $device['next_maintenance_at'],
            'status' => 'pending',
            'response_token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->eventByToken($token) ?? [];
    }

    private function dueMailHtml(array $device, array $event): string
    {
        $token = (string) $event['response_token'];
        $done = url('/maintenance/respond?token=' . urlencode($token) . '&status=done');
        $notDone = url('/maintenance/respond?token=' . urlencode($token) . '&status=not_done');
        $rescheduled = url('/maintenance/respond?token=' . urlencode($token) . '&status=rescheduled');

        return '<p><strong>' . e($device['code']) . '</strong> kodlu cihaz icin bakim gunu geldi.</p>'
            . '<p>Lutfen 48 saat icinde asagidaki butonlardan biriyle durum bildirin.</p>'
            . '<p>'
            . '<a href="' . e($done) . '" style="display:inline-block;padding:10px 14px;background:#087443;color:#fff;text-decoration:none;border-radius:6px;">Yapildi</a> '
            . '<a href="' . e($notDone) . '" style="display:inline-block;padding:10px 14px;background:#b42318;color:#fff;text-decoration:none;border-radius:6px;">Yapilmadi</a> '
            . '<a href="' . e($rescheduled) . '" style="display:inline-block;padding:10px 14px;background:#b45309;color:#fff;text-decoration:none;border-radius:6px;">Baska zamana planlandi</a>'
            . '</p>';
    }

    private function sendHazardMail(array $device, array $event): void
    {
        $ackToken = bin2hex(random_bytes(32));
        Database::pdo()->prepare('UPDATE maintenance_events SET read_ack_token = :token, hazard_sent_at = :sent_at, updated_at = :updated_at WHERE id = :id')
            ->execute(['token' => $ackToken, 'sent_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'id' => $event['id']]);

        $ackUrl = url('/maintenance/read-ack?token=' . urlencode($ackToken));
        $hazard = trim((string) ($device['hazard_note'] ?? ''));
        if ($hazard === '') {
            $hazard = 'Bakimin yapilmamasi ariza riskini, plansiz durusu, kalite problemlerini ve is guvenligi risklerini artirabilir.';
        }

        $html = '<p><strong>' . e($device['code']) . '</strong> kodlu cihaz icin bakim yapilmadi olarak isaretlendi.</p>'
            . '<p>' . nl2br(e($hazard)) . '</p>'
            . '<p><a href="' . e($ackUrl) . '" style="display:inline-block;padding:10px 14px;background:#102522;color:#fff;text-decoration:none;border-radius:6px;">Okudum, bilgi sahibiyim</a></p>';

        $this->notifications->mail(DeviceRepository::emailsFromDevice($device), $device['code'] . ' bakim riski bildirimi', $html, 'maintenance.hazard', (int) $device['id'], (int) $event['id'], $ackToken);
    }

    private function warningDays(array $device): array
    {
        $raw = (string) ($device['notify_before_days'] ?? '');
        $days = array_map('intval', preg_split('/[\s,;]+/', $raw) ?: []);
        $days = array_values(array_unique(array_filter($days, static fn (int $day): bool => $day >= 0)));

        return $days ?: Config::load()->get('app.maintenance_warning_days', [30, 14, 7, 3, 1]);
    }

    private function daysUntil(string $date): int
    {
        $today = new DateTimeImmutable(date('Y-m-d'));
        $target = new DateTimeImmutable($date);

        return (int) $today->diff($target)->format('%r%a');
    }
}

