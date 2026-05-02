<div class="section-title">
    <div>
        <h1>Admin Panel</h1>
        <p class="muted">Cihaz kimlikleri, QR etiketleri, bakim uyarilari ve yedekler.</p>
    </div>
    <div class="actions">
        <a class="btn primary" href="/admin/devices/create">Yeni Cihaz</a>
        <a class="btn" href="/admin/backups">Yedekler</a>
    </div>
</div>

<div class="grid cols-4" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="stat"><span class="muted">Toplam cihaz</span><strong><?= e((string) $deviceCount) ?></strong></div>
    <div class="stat"><span class="muted">30 gun icinde bakim</span><strong><?= e((string) $dueSoonCount) ?></strong></div>
    <div class="stat"><span class="muted">Cevap bekleyen</span><strong><?= e((string) $pendingCount) ?></strong></div>
    <div class="stat"><span class="muted">Silinen havuzu</span><strong><?= e((string) $deletedDeviceCount) ?></strong></div>
</div>

<div class="grid cols-2" style="margin-top:18px;">
    <section class="panel">
        <div class="section-title">
            <h2>Yaklasan Bakimlar</h2>
            <a class="btn" href="/admin/devices">Tum cihazlar</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>Seri No</th>
                        <th>Bakim Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dueSoon as $device): ?>
                    <tr>
                        <td><a href="/admin/devices/<?= e((string) $device['id']) ?>"><?= e($device['code']) ?></a></td>
                        <td><?= e($device['serial_number']) ?></td>
                        <td><?= e($device['next_maintenance_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$dueSoon): ?>
                    <tr><td colspan="3" class="muted">Yaklasan bakim yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="section-title">
            <h2>Son Cihazlar</h2>
            <a class="btn" href="/admin/devices/create">Ekle</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>Kurulum</th>
                        <th>Periyot</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($latestDevices as $device): ?>
                    <tr>
                        <td><a href="/admin/devices/<?= e((string) $device['id']) ?>"><?= e($device['code']) ?></a></td>
                        <td><?= e($device['installed_at']) ?></td>
                        <td><?= e((string) $device['maintenance_period_days']) ?> gun</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$latestDevices): ?>
                    <tr><td colspan="3" class="muted">Henuz cihaz eklenmedi.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
