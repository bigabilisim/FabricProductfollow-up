<?php
$scanUrl = url('/scan?token=' . urlencode((string) $device['qr_token']));
?>
<div class="section-title">
    <div>
        <h1><?= e($device['code']) ?></h1>
        <p class="muted">Seri no: <?= e($device['serial_number']) ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>/edit" onclick="return confirm('Bu cihazi duzenlemek istiyor musunuz?');">Duzenle</a>
        <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>/qr.svg">QR SVG</a>
        <a class="btn primary" href="/admin/devices/<?= e((string) $device['id']) ?>/label">Etiket / PDF</a>
    </div>
</div>

<div class="grid cols-2">
    <section class="panel">
        <h2>Cihaz Bilgileri</h2>
        <table>
            <tr><th>Kod</th><td><?= e($device['code']) ?></td></tr>
            <tr><th>Seri numarasi</th><td><?= e($device['serial_number']) ?></td></tr>
            <tr><th>Kurulum tarihi</th><td><?= e($device['installed_at']) ?></td></tr>
            <tr><th>Bakim periyodu</th><td><?= e((string) $device['maintenance_period_days']) ?> gun</td></tr>
            <tr><th>Son bakim</th><td><?= e($device['last_maintenance_at'] ?: '-') ?></td></tr>
            <tr><th>Sonraki bakim</th><td><?= e($device['next_maintenance_at']) ?></td></tr>
            <tr><th>Bildirim gunleri</th><td><?= e($device['notify_before_days']) ?></td></tr>
            <tr><th>Yetkili mail</th><td><?= nl2br(e($device['responsible_emails'])) ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h2>QR</h2>
        <div class="qr-preview">
            <img src="/admin/devices/<?= e((string) $device['id']) ?>/qr.svg" alt="QR">
        </div>
        <p class="muted" style="word-break:break-all;"><?= e($scanUrl) ?></p>
    </section>
</div>

<div class="panel" style="margin-top:18px;">
    <h2>Notlar ve Risk Metni</h2>
    <p><?= nl2br(e($device['notes'] ?: '-')) ?></p>
    <h3>Bakim yapilmazsa</h3>
    <p><?= nl2br(e($device['hazard_note'] ?: 'Standart risk metni kullanilir.')) ?></p>
    <form method="post" action="/admin/devices/<?= e((string) $device['id']) ?>/delete" onsubmit="return confirm('Bu cihaz silinenler havuzuna tasinsin mi?');">
        <?= csrf_field() ?>
        <button class="btn danger" type="submit">Cihazi Sil</button>
    </form>
</div>
