<?php

use App\Core\Config;

$config = Config::load();
$flash = flash();
$title = $title ?? (string) $config->get('app.site_name', 'Fabrika QR');
$logoPath = (string) $config->get('app.logo_path', '');
$logoUrl = $logoPath !== '' ? app_path($logoPath) : '';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102522">
    <?php if (current_user()): ?>
        <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php endif; ?>
    <title><?= e($title) ?> - <?= e((string) $config->get('app.site_name', 'Fabrika QR')) ?></title>
    <link rel="manifest" href="<?= e(app_path('/manifest.webmanifest')) ?>">
    <?php if ($logoUrl !== ''): ?>
        <link rel="icon" href="<?= e($logoUrl) ?>">
        <link rel="apple-touch-icon" href="<?= e($logoUrl) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= e(app_path('/assets/pwa-icon.svg')) ?>">
        <link rel="apple-touch-icon" href="<?= e(app_path('/assets/pwa-icon.svg')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="<?= e(app_path('/assets/pwa.js')) ?>" defer></script>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="brand">
            <?php if ($logoPath !== ''): ?>
                <img class="brand-logo" src="<?= e($logoUrl) ?>" alt="<?= e((string) $config->get('app.site_name', 'Fabrika QR')) ?> logosu">
            <?php endif; ?>
            <span class="brand-text">
                <span><?= e((string) $config->get('app.site_name', 'Fabrika QR')) ?></span>
                <small>QR cihaz kimligi ve bakim takip - v<?= e((string) $config->get('app.version', '1.0V')) ?></small>
            </span>
        </div>
        <nav class="nav">
            <?php if (current_user()): ?>
                <a href="/admin">Panel</a>
                <a href="/admin/devices">Cihazlar</a>
                <a href="/admin/templates?type=mail">Mail Sablonlari</a>
                <a href="/admin/templates?type=report">Raporlar</a>
                <a href="/admin/backups">Yedekler</a>
                <a href="/admin/settings">Ayarlar</a>
                <form method="post" action="/logout" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit">Cikis</button>
                </form>
            <?php else: ?>
                <a href="/login">Admin Giris</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= e($flash['type'] === 'error' ? 'error' : '') ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>
</body>
</html>
