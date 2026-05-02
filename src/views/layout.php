<?php

use App\Core\Config;

$config = Config::load();
$flash = flash();
$title = $title ?? (string) $config->get('app.site_name', 'Fabrika QR');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - <?= e((string) $config->get('app.site_name', 'Fabrika QR')) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="brand">
            <span><?= e((string) $config->get('app.site_name', 'Fabrika QR')) ?></span>
            <small>QR cihaz kimligi ve bakim takip - v<?= e((string) $config->get('app.version', '1.0V')) ?></small>
        </div>
        <nav class="nav">
            <?php if (current_user()): ?>
                <a href="/admin">Panel</a>
                <a href="/admin/devices">Cihazlar</a>
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
