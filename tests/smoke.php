<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Repositories\DeviceRepository;
use App\Services\QrCode;

$code = DeviceRepository::buildCode('ana', 'tr', 2026, 55);
if ($code !== 'ANA-TR-2026-55') {
    throw new RuntimeException('Device code format failed: ' . $code);
}

$nextMaintenance = DeviceRepository::nextMaintenanceDate('2026-01-01', 180);
if ($nextMaintenance !== '2026-06-30') {
    throw new RuntimeException('Maintenance date calculation failed: ' . $nextMaintenance);
}

$svg = (new QrCode())->svg('https://example.com/scan?token=test');
if (!str_contains($svg, '<svg') || !str_contains($svg, '<path')) {
    throw new RuntimeException('QR SVG generation failed.');
}

echo "Smoke test passed.\n";

