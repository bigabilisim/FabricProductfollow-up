<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Config;
use App\Services\MaintenanceService;

if (!Config::load()->isInstalled()) {
    fwrite(STDERR, "Application is not installed.\n");
    exit(1);
}

$result = (new MaintenanceService())->runScheduledNotifications();
echo 'maintenance_notifications ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;

