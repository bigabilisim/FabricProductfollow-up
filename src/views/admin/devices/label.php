<div class="section-title no-print">
    <div>
        <h1>Etiket</h1>
        <p class="muted">Tarayicinin yazdir komutuyla fiziksel cikti veya PDF alabilirsiniz.</p>
    </div>
    <div class="actions">
        <button class="btn primary" onclick="window.print()">Yazdir / PDF</button>
        <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>">Detay</a>
    </div>
</div>

<div class="panel label-sheet">
    <div class="label-card">
        <img src="<?= e($qrDataUri) ?>" alt="QR">
        <div>
            <h2 style="margin:0 0 8px;"><?= e($device['code']) ?></h2>
            <p style="margin:0 0 4px;">Seri No: <?= e($device['serial_number']) ?></p>
            <p style="margin:0 0 4px;">Kurulum: <?= e($device['installed_at']) ?></p>
            <p style="margin:0;">Bakim: <?= e($device['next_maintenance_at']) ?></p>
        </div>
    </div>
</div>

