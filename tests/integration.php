<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$configPath = $basePath . '/config/config.php';

if (is_file($configPath)) {
    throw new RuntimeException('config/config.php exists. Refusing to overwrite a real installation config.');
}

$testConfig = require $basePath . '/config/config.example.php';
$databaseName = 'factory_qr_test_' . bin2hex(random_bytes(4));
$testConfig['app']['installed'] = true;
$testConfig['app']['base_url'] = 'http://127.0.0.1:8000';
$testConfig['database'] = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => $databaseName,
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];
$databaseConfig = $testConfig['database'];

file_put_contents($configPath, "<?php\n\nreturn " . var_export($testConfig, true) . ";\n");

try {
    require $basePath . '/src/bootstrap.php';

    $deadline = time() + 60;
    do {
        try {
            $serverPdo = App\Core\Database::connectWithoutDatabase($databaseConfig);
            break;
        } catch (Throwable $exception) {
            if (time() >= $deadline) {
                throw $exception;
            }
            sleep(2);
        }
    } while (true);

    $serverPdo->exec('CREATE DATABASE `' . str_replace('`', '``', $databaseName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = App\Core\Database::pdo();

    $schema = file_get_contents($basePath . '/database/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
        $pdo->exec($statement);
    }

    $repo = new App\Repositories\DeviceRepository();
    $id = $repo->create([
        'company_code' => 'ANA',
        'country_code' => 'TR',
        'production_year' => 2026,
        'machine_no' => 55,
        'serial_number' => 'SN-TEST-001',
        'installed_at' => '2026-01-01',
        'maintenance_period_days' => 180,
        'notify_before_days' => '30,14,7,3,1',
        'responsible_emails' => 'bakim@example.com',
        'hazard_note' => 'Test risk metni',
        'notes' => 'Test cihazi',
    ]);

    $device = $repo->find($id);
    if (!$device || $device['code'] !== 'ANA-TR-2026-1') {
        throw new RuntimeException('Device insert/read failed.');
    }

    if ($device['next_maintenance_at'] !== '2026-06-30') {
        throw new RuntimeException('Next maintenance date failed: ' . $device['next_maintenance_at']);
    }

    $secondId = $repo->create([
        'company_code' => 'ANA',
        'country_code' => 'TR',
        'production_year' => 2026,
        'machine_no' => 55,
        'serial_number' => 'SN-TEST-002',
        'installed_at' => '2026-01-01',
        'maintenance_period_days' => 180,
        'notify_before_days' => '30,14,7,3,1',
        'responsible_emails' => 'bakim@example.com',
    ]);

    $secondDevice = $repo->find($secondId);
    if (!$secondDevice || $secondDevice['code'] !== 'ANA-TR-2026-2') {
        throw new RuntimeException('Automatic machine number sequence failed.');
    }

    $thirdId = $repo->create([
        'company_code' => 'ANA',
        'country_code' => 'DE',
        'production_year' => 2026,
        'machine_no' => 99,
        'serial_number' => 'SN-TEST-003',
        'installed_at' => '2026-01-01',
        'maintenance_period_days' => 180,
        'notify_before_days' => '30,14,7,3,1',
        'responsible_emails' => 'bakim@example.com',
    ]);

    $thirdDevice = $repo->find($thirdId);
    if (!$thirdDevice || $thirdDevice['code'] !== 'ANA-DE-2026-1') {
        throw new RuntimeException('Country/year machine number memory failed.');
    }

    $repo->update($secondId, [
        'company_code' => 'ANA',
        'country_code' => 'DE',
        'production_year' => 2026,
        'machine_no' => 1,
        'serial_number' => 'SN-TEST-002',
        'installed_at' => '2026-01-01',
        'maintenance_period_days' => 180,
        'notify_before_days' => '30,14,7,3,1',
        'responsible_emails' => 'bakim@example.com',
    ]);

    $secondDevice = $repo->find($secondId);
    if (!$secondDevice || $secondDevice['code'] !== 'ANA-DE-2026-2') {
        throw new RuntimeException('Machine number recalculation on country/year change failed.');
    }

    $repo->delete($id);
    if ($repo->find($id) !== null) {
        throw new RuntimeException('Soft deleted device is still visible in active records.');
    }

    if ($repo->countDeleted() !== 1) {
        throw new RuntimeException('Deleted device pool count failed.');
    }

    $deletedDevice = $repo->findIncludingDeleted($id);
    if (!$deletedDevice || empty($deletedDevice['deleted_at'])) {
        throw new RuntimeException('Deleted device was not retained in the pool.');
    }

    $repo->restore($id);
    if (!$repo->find($id) || $repo->countDeleted() !== 0) {
        throw new RuntimeException('Device restore failed.');
    }

    echo "Integration test passed.\n";
} finally {
    if (isset($serverPdo, $databaseName)) {
        $serverPdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $databaseName) . '`');
    }
    @unlink($configPath);
}
