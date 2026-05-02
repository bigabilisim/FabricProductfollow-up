<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Config;
use App\Services\BackupService;

if (!Config::load()->isInstalled()) {
    fwrite(STDERR, "Application is not installed.\n");
    exit(1);
}

$backup = (new BackupService())->createAndMail();
echo 'daily_backup ' . json_encode($backup, JSON_UNESCAPED_UNICODE) . PHP_EOL;

