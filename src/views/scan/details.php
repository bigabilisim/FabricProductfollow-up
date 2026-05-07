<div class="panel" style="max-width:760px;margin:0 auto;">
    <div class="section-title">
        <div>
            <h1><?= e($device['code']) ?></h1>
            <p class="muted">Yetkili linki ile acilan cihaz bilgileri</p>
        </div>
        <span class="badge">48 saat aktif</span>
    </div>

    <table>
        <tr><th>Seri numarasi</th><td><?= e($device['serial_number']) ?></td></tr>
        <tr><th>Kurulum tarihi</th><td><?= e($device['installed_at']) ?></td></tr>
        <tr><th>Bakim periyodu</th><td><?= e((string) $device['maintenance_period_days']) ?> gun</td></tr>
        <tr><th>Son bakim</th><td><?= e($device['last_maintenance_at'] ?: '-') ?></td></tr>
        <tr><th>Sonraki bakim</th><td><?= e($device['next_maintenance_at']) ?></td></tr>
        <tr><th>Notlar</th><td><?= nl2br(e($device['notes'] ?: '-')) ?></td></tr>
    </table>
</div>
