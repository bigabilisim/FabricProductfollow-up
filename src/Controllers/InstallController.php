<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Services\Installer;
use Throwable;

final class InstallController
{
    public function show(): void
    {
        if (Config::load()->isInstalled() && ($_GET['force'] ?? '') !== '1') {
            redirect('/login');
        }

        view('install/show', [
            'title' => 'Kurulum',
            'defaultBaseUrl' => current_base_url(),
            'defaultBasePath' => app_base_path(),
        ], 'install');
    }

    public function store(): void
    {
        verify_csrf();

        try {
            (new Installer())->install($_POST, $_FILES['backup_file'] ?? null);
            flash('Kurulum tamamlandi. Admin kullanicinizla giris yapabilirsiniz.');
            redirect('/login');
        } catch (Throwable $exception) {
            flash('Kurulum tamamlanamadi: ' . $exception->getMessage(), 'error');
            redirect('/install');
        }
    }

    public function testDatabase(): void
    {
        verify_csrf();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $database = [
                'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
                'port' => (int) ($_POST['db_port'] ?? 3306),
                'name' => trim((string) ($_POST['db_name'] ?? '')),
                'user' => trim((string) ($_POST['db_user'] ?? 'root')),
                'password' => (string) ($_POST['db_password'] ?? ''),
                'charset' => 'utf8mb4',
            ];

            $pdo = Database::connectWithoutDatabase($database);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1');
            $statement->execute(['name' => $database['name']]);
            $exists = (bool) $statement->fetchColumn();

            echo json_encode([
                'ok' => true,
                'message' => $exists
                    ? 'Baglanti basarili. Veritabani bulundu. MariaDB/MySQL surumu: ' . $version
                    : 'Baglanti basarili. Veritabani bulunamadi; kurulum sirasinda olusturulacak. MariaDB/MySQL surumu: ' . $version,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Baglanti basarisiz: ' . $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
