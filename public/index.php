<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/bootstrap.php';

use App\Core\Config;
use App\Core\Router;

$config = Config::load();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = app_base_path();

if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
    $path = substr($path, strlen($basePath));
    $path = $path === '' ? '/' : $path;
}

if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

if (!$config->isInstalled() && !str_starts_with($path, '/install')) {
    redirect('/install');
}

$router = new Router();
$registerRoutes = require BASE_PATH . '/src/routes.php';
$registerRoutes($router);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$router->dispatch($method === 'HEAD' ? 'GET' : $method, $path);
