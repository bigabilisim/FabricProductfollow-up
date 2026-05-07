<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DeviceController;
use App\Controllers\InstallController;
use App\Controllers\MaintenanceController;
use App\Controllers\PwaController;
use App\Controllers\ScanController;
use App\Controllers\TemplateController;
use App\Controllers\WebPushController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', static fn () => redirect('/admin'));
    $router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
    $router->get('/offline', [PwaController::class, 'offline']);

    $router->get('/install', [InstallController::class, 'show']);
    $router->post('/install/test-db', [InstallController::class, 'testDatabase']);
    $router->post('/install', [InstallController::class, 'store']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/admin', [AdminController::class, 'dashboard']);
    $router->get('/admin/backups', [AdminController::class, 'backups']);
    $router->post('/admin/backups/create', [AdminController::class, 'createBackup']);
    $router->post('/admin/backups/mail', [AdminController::class, 'mailBackup']);
    $router->get('/admin/settings', [AdminController::class, 'settings']);
    $router->post('/admin/settings', [AdminController::class, 'updateSettings']);

    $router->get('/admin/templates', [TemplateController::class, 'index']);
    $router->get('/admin/templates/test-platform', [TemplateController::class, 'testPlatform']);
    $router->post('/admin/templates/test-platform', [TemplateController::class, 'sendTestPlatform']);
    $router->get('/admin/templates/create', [TemplateController::class, 'create']);
    $router->post('/admin/templates', [TemplateController::class, 'store']);
    $router->get('/admin/templates/{id}/edit', [TemplateController::class, 'edit']);
    $router->post('/admin/templates/{id}', [TemplateController::class, 'update']);
    $router->post('/admin/templates/{id}/test-mail', [TemplateController::class, 'testMail']);
    $router->post('/admin/templates/{id}/delete', [TemplateController::class, 'delete']);
    $router->get('/admin/templates/{id}/preview', [TemplateController::class, 'preview']);

    $router->get('/web-push/public-key', [WebPushController::class, 'publicKey']);
    $router->post('/web-push/subscribe', [WebPushController::class, 'subscribe']);
    $router->post('/web-push/unsubscribe', [WebPushController::class, 'unsubscribe']);
    $router->post('/web-push/test', [WebPushController::class, 'test']);

    $router->get('/admin/devices', [DeviceController::class, 'index']);
    $router->get('/admin/devices/create', [DeviceController::class, 'create']);
    $router->post('/admin/devices', [DeviceController::class, 'store']);
    $router->get('/admin/devices/deleted', [DeviceController::class, 'deleted']);
    $router->get('/admin/devices/next-code', [DeviceController::class, 'nextCode']);
    $router->get('/admin/devices/{id}', [DeviceController::class, 'show']);
    $router->get('/admin/devices/{id}/edit', [DeviceController::class, 'edit']);
    $router->post('/admin/devices/{id}', [DeviceController::class, 'update']);
    $router->post('/admin/devices/{id}/delete', [DeviceController::class, 'delete']);
    $router->post('/admin/devices/{id}/restore', [DeviceController::class, 'restore']);
    $router->post('/admin/devices/{id}/purge', [DeviceController::class, 'purge']);
    $router->get('/admin/devices/{id}/qr.svg', [DeviceController::class, 'qrSvg']);
    $router->get('/admin/devices/{id}/label', [DeviceController::class, 'label']);

    $router->get('/scan', [ScanController::class, 'show']);
    $router->post('/scan/request-link', [ScanController::class, 'requestLink']);
    $router->post('/scan/request-code', [ScanController::class, 'requestLink']);
    $router->get('/device/{id}/details', [ScanController::class, 'details']);

    $router->get('/maintenance/respond', [MaintenanceController::class, 'respond']);
    $router->post('/maintenance/respond', [MaintenanceController::class, 'storeResponse']);
    $router->get('/maintenance/read-ack', [MaintenanceController::class, 'readAck']);
};
