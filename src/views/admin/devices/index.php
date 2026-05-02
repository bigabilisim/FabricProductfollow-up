<div class="section-title">
    <div>
        <h1>Cihazlar</h1>
        <p class="muted">Uretim kimligi, QR kodu ve bakim periyodu kayitlari.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/devices/deleted">Silinenler (<?= e((string) $deletedCount) ?>)</a>
        <a class="btn primary" href="/admin/devices/create">Yeni Cihaz</a>
    </div>
</div>

<div class="panel">
    <form method="get" action="/admin/devices" class="actions" style="margin-bottom:14px;">
        <input name="q" value="<?= e($search) ?>" placeholder="Kod veya seri no ara" style="max-width:320px;">
        <button class="btn" type="submit">Ara</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kod</th>
                    <th>Seri No</th>
                    <th>Kurulum</th>
                    <th>Sonraki Bakim</th>
                    <th>Yetkili Mail</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $device): ?>
                <tr>
                    <td><a href="/admin/devices/<?= e((string) $device['id']) ?>"><?= e($device['code']) ?></a></td>
                    <td><?= e($device['serial_number']) ?></td>
                    <td><?= e($device['installed_at']) ?></td>
                    <td><?= e($device['next_maintenance_at']) ?></td>
                    <td><?= e($device['responsible_emails']) ?></td>
                    <td class="actions">
                        <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>/edit" onclick="return confirm('Bu cihazi duzenlemek istiyor musunuz?');">Duzenle</a>
                        <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>/qr.svg">SVG</a>
                        <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>/label">Etiket</a>
                        <form method="post" action="/admin/devices/<?= e((string) $device['id']) ?>/delete" class="inline" onsubmit="return confirm('Bu cihaz silinenler havuzuna tasinsin mi?');">
                            <?= csrf_field() ?>
                            <button class="btn danger" type="submit">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$devices): ?>
                <tr><td colspan="6" class="muted">Cihaz kaydi bulunamadi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
