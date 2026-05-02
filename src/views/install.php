<?php
$config = App\Core\Config::load();
$flash = flash();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Kurulum') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="install">
    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type'] === 'error' ? 'error' : '') ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>
</body>
</html>
