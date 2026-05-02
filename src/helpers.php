<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\View;

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $configured = Config::load()->get('app.base_path');
    if (is_string($configured) && $configured !== '') {
        $basePath = '/' . trim($configured, '/');
        return $basePath === '/' ? '' : $basePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');

    foreach (['/public', '/install'] as $suffix) {
        if ($dir === $suffix) {
            $dir = '';
            break;
        }

        if (str_ends_with($dir, $suffix)) {
            $dir = substr($dir, 0, -strlen($suffix));
            break;
        }
    }

    $basePath = $dir === '/' ? '' : $dir;
    return $basePath;
}

function app_path(string $path = ''): string
{
    $base = app_base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }

    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

function current_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return rtrim($scheme . '://' . $host . app_path(''), '/');
}

function url(string $path = ''): string
{
    $config = Config::load();
    $configuredBase = (string) $config->get('app.base_url', '');
    $base = $config->isInstalled() && $configuredBase !== '' ? rtrim($configuredBase, '/') : current_base_url();

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $location = preg_match('#^https?://#i', $path) ? $path : app_path($path);
    header('Location: ' . $location, true, 302);
    exit;
}

function view(string $template, array $data = [], string $layout = 'layout'): void
{
    View::render($template, $data, $layout);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        unset($_SESSION['_csrf']);
        flash('Oturum guvenlik dogrulamasi basarisiz oldu. Lutfen formu tekrar gonderin.', 'error');

        $refererPath = csrf_referer_path();
        redirect($refererPath ?: '/login');
    }
}

function csrf_referer_path(): ?string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer === '') {
        return null;
    }

    $refererHost = parse_url($referer, PHP_URL_HOST);
    $refererPort = parse_url($referer, PHP_URL_PORT);
    $refererAuthority = is_string($refererHost) ? strtolower($refererHost . (is_int($refererPort) ? ':' . $refererPort : '')) : '';
    $currentAuthority = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($refererAuthority === '' || $refererAuthority !== $currentAuthority) {
        return null;
    }

    $path = parse_url($referer, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }

    $base = app_base_path();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    $query = parse_url($referer, PHP_URL_QUERY);
    return $path . (is_string($query) && $query !== '' ? '?' . $query : '');
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $flash;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): void
{
    if (!current_user()) {
        redirect('/login');
    }
}
