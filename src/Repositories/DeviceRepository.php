<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class DeviceRepository
{
    private static bool $trashSchemaChecked = false;

    public function all(?string $search = null): array
    {
        $pdo = Database::pdo();
        $this->ensureTrashSchema();
        if ($search) {
            $statement = $pdo->prepare('SELECT * FROM devices WHERE deleted_at IS NULL AND (code LIKE :q OR serial_number LIKE :q) ORDER BY created_at DESC');
            $statement->execute(['q' => '%' . $search . '%']);
            return $statement->fetchAll();
        }

        return $pdo->query('SELECT * FROM devices WHERE deleted_at IS NULL ORDER BY created_at DESC')->fetchAll();
    }

    public function deleted(?string $search = null): array
    {
        $pdo = Database::pdo();
        $this->ensureTrashSchema();
        if ($search) {
            $statement = $pdo->prepare('SELECT * FROM devices WHERE deleted_at IS NOT NULL AND (code LIKE :q OR serial_number LIKE :q) ORDER BY deleted_at DESC');
            $statement->execute(['q' => '%' . $search . '%']);
            return $statement->fetchAll();
        }

        return $pdo->query('SELECT * FROM devices WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')->fetchAll();
    }

    public function latest(int $limit = 5): array
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE id = :id AND deleted_at IS NULL');
        $statement->execute(['id' => $id]);
        $device = $statement->fetch();

        return $device ?: null;
    }

    public function findIncludingDeleted(int $id): ?array
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE id = :id');
        $statement->execute(['id' => $id]);
        $device = $statement->fetch();

        return $device ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE qr_token = :token AND deleted_at IS NULL');
        $statement->execute(['token' => $token]);
        $device = $statement->fetch();

        return $device ?: null;
    }

    public function create(array $data): int
    {
        $data = $this->withAutomaticIdentity($data);
        $data = $this->normalize($data);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['qr_token'] = bin2hex(random_bytes(32));

        $sql = 'INSERT INTO devices (
            code, company_code, country_code, production_year, machine_no, serial_number,
            installed_at, maintenance_period_days, notify_before_days, responsible_emails,
            hazard_note, notes, qr_token, next_maintenance_at, created_at
        ) VALUES (
            :code, :company_code, :country_code, :production_year, :machine_no, :serial_number,
            :installed_at, :maintenance_period_days, :notify_before_days, :responsible_emails,
            :hazard_note, :notes, :qr_token, :next_maintenance_at, :created_at
        )';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($data);

        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data = $this->withAutomaticIdentity($data, $id);
        $data = $this->normalize($data);
        $data['id'] = $id;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $sql = 'UPDATE devices SET
            code = :code,
            company_code = :company_code,
            country_code = :country_code,
            production_year = :production_year,
            machine_no = :machine_no,
            serial_number = :serial_number,
            installed_at = :installed_at,
            maintenance_period_days = :maintenance_period_days,
            notify_before_days = :notify_before_days,
            responsible_emails = :responsible_emails,
            hazard_note = :hazard_note,
            notes = :notes,
            next_maintenance_at = :next_maintenance_at,
            updated_at = :updated_at
        WHERE id = :id';

        Database::pdo()->prepare($sql)->execute($data);
    }

    public function delete(int $id): void
    {
        $this->ensureTrashSchema();
        Database::pdo()->prepare('UPDATE devices SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL')->execute([
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function restore(int $id): void
    {
        $this->ensureTrashSchema();
        Database::pdo()->prepare('UPDATE devices SET deleted_at = NULL, updated_at = :updated_at WHERE id = :id AND deleted_at IS NOT NULL')->execute([
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function purge(int $id): void
    {
        $this->ensureTrashSchema();
        Database::pdo()->prepare('DELETE FROM devices WHERE id = :id AND deleted_at IS NOT NULL')->execute(['id' => $id]);
    }

    public function count(): int
    {
        $this->ensureTrashSchema();
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL')->fetchColumn();
    }

    public function countDeleted(): int
    {
        $this->ensureTrashSchema();
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM devices WHERE deleted_at IS NOT NULL')->fetchColumn();
    }

    public function countDueSoon(int $days = 30): int
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL AND next_maintenance_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)');
        $statement->bindValue('days', $days, PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function dueSoon(int $days = 30): array
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT * FROM devices WHERE deleted_at IS NULL AND next_maintenance_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY) ORDER BY next_maintenance_at ASC');
        $statement->bindValue('days', $days, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function suggestIdentity(string $countryCode, int $year, ?int $deviceId = null): array
    {
        $companyCode = 'ANA';
        $countryCode = strtoupper(trim($countryCode) ?: 'TR');
        $year = $year > 0 ? $year : (int) date('Y');

        $device = $deviceId ? $this->findIncludingDeleted($deviceId) : null;
        if ($device) {
            $companyCode = (string) $device['company_code'];
            if (
                strtoupper((string) $device['country_code']) === $countryCode
                && (int) $device['production_year'] === $year
            ) {
                $machineNo = (int) $device['machine_no'];
                return [
                    'company_code' => $companyCode,
                    'country_code' => $countryCode,
                    'production_year' => $year,
                    'machine_no' => $machineNo,
                    'code' => self::buildCode($companyCode, $countryCode, $year, $machineNo),
                ];
            }
        }

        $machineNo = $this->nextMachineNo($companyCode, $countryCode, $year);

        return [
            'company_code' => $companyCode,
            'country_code' => $countryCode,
            'production_year' => $year,
            'machine_no' => $machineNo,
            'code' => self::buildCode($companyCode, $countryCode, $year, $machineNo),
        ];
    }

    public static function buildCode(string $companyCode, string $countryCode, int $year, int $machineNo): string
    {
        $companyCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $companyCode) ?: 'ANA');
        $countryCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $countryCode) ?: 'TR');

        return "{$companyCode}-{$countryCode}-{$year}-{$machineNo}";
    }

    public static function nextMaintenanceDate(string $fromDate, int $periodDays): string
    {
        $date = new DateTimeImmutable($fromDate);
        return $date->add(new DateInterval('P' . max(1, $periodDays) . 'D'))->format('Y-m-d');
    }

    public static function emailsFromDevice(array $device): array
    {
        $raw = str_replace(["\r\n", "\r", ';'], ["\n", "\n", ','], (string) ($device['responsible_emails'] ?? ''));
        $emails = preg_split('/[\s,]+/', $raw) ?: [];

        return array_values(array_unique(array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    private function normalize(array $data): array
    {
        $companyCode = strtoupper(trim((string) ($data['company_code'] ?? 'ANA')));
        $countryCode = strtoupper(trim((string) ($data['country_code'] ?? 'TR')));
        $year = (int) ($data['production_year'] ?? date('Y'));
        $machineNo = (int) ($data['machine_no'] ?? 1);
        $installedAt = (string) ($data['installed_at'] ?? date('Y-m-d'));
        $period = max(1, (int) ($data['maintenance_period_days'] ?? 180));

        return [
            'code' => self::buildCode($companyCode, $countryCode, $year, $machineNo),
            'company_code' => $companyCode,
            'country_code' => $countryCode,
            'production_year' => $year,
            'machine_no' => $machineNo,
            'serial_number' => trim((string) ($data['serial_number'] ?? '')),
            'installed_at' => $installedAt,
            'maintenance_period_days' => $period,
            'notify_before_days' => trim((string) ($data['notify_before_days'] ?? '30,14,7,3,1')),
            'responsible_emails' => trim((string) ($data['responsible_emails'] ?? '')),
            'hazard_note' => trim((string) ($data['hazard_note'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'next_maintenance_at' => self::nextMaintenanceDate($installedAt, $period),
        ];
    }

    private function withAutomaticIdentity(array $data, ?int $deviceId = null): array
    {
        $companyCode = 'ANA';
        $countryCode = strtoupper(trim((string) ($data['country_code'] ?? 'TR')) ?: 'TR');
        $year = (int) ($data['production_year'] ?? date('Y'));

        $device = $deviceId ? $this->findIncludingDeleted($deviceId) : null;
        if ($device) {
            $companyCode = (string) $device['company_code'];
            if (
                strtoupper((string) $device['country_code']) === $countryCode
                && (int) $device['production_year'] === $year
            ) {
                $data['machine_no'] = (int) $device['machine_no'];
            } else {
                $data['machine_no'] = $this->nextMachineNo($companyCode, $countryCode, $year);
            }
        } else {
            $data['machine_no'] = $this->nextMachineNo($companyCode, $countryCode, $year);
        }

        $data['company_code'] = $companyCode;
        $data['country_code'] = $countryCode;
        $data['production_year'] = $year;

        return $data;
    }

    private function nextMachineNo(string $companyCode, string $countryCode, int $year): int
    {
        $this->ensureTrashSchema();
        $statement = Database::pdo()->prepare('SELECT COALESCE(MAX(machine_no), 0) + 1 FROM devices WHERE company_code = :company_code AND country_code = :country_code AND production_year = :production_year');
        $statement->execute([
            'company_code' => strtoupper(trim($companyCode) ?: 'ANA'),
            'country_code' => strtoupper(trim($countryCode) ?: 'TR'),
            'production_year' => $year,
        ]);

        return max(1, (int) $statement->fetchColumn());
    }

    private function ensureTrashSchema(): void
    {
        if (self::$trashSchemaChecked) {
            return;
        }

        $pdo = Database::pdo();
        $column = $pdo->query("SHOW COLUMNS FROM devices LIKE 'deleted_at'")->fetch();
        if (!$column) {
            $pdo->exec('ALTER TABLE devices ADD COLUMN deleted_at DATETIME NULL AFTER next_maintenance_at');
            $pdo->exec('ALTER TABLE devices ADD INDEX idx_devices_deleted_at (deleted_at)');
        }

        self::$trashSchemaChecked = true;
    }
}
