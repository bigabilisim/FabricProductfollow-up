<div class="section-title">
    <div>
        <h1>Silinen Cihazlar</h1>
        <p class="muted">Silinen cihazlar burada tutulur; gerekirse geri alinabilir veya kalici olarak silinebilir.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/devices">Aktif Cihazlar</a>
        <a class="btn primary" href="/admin/devices/create">Yeni Cihaz</a>
    </div>
</div>

<div class="panel">
    <form method="get" action="/admin/devices/deleted" class="actions" style="margin-bottom:14px;">
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
                    <th>Silinme Tarihi</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $device): ?>
                <tr>
                    <td><?= e($device['code']) ?></td>
                    <td><?= e($device['serial_number']) ?></td>
                    <td><?= e($device['installed_at']) ?></td>
                    <td><?= e($device['next_maintenance_at']) ?></td>
                    <td><?= e($device['deleted_at'] ?: '-') ?></td>
                    <td class="actions">
                        <form method="post" action="/admin/devices/<?= e((string) $device['id']) ?>/restore" class="inline" onsubmit="return confirm('Bu cihaz aktif listeye geri alinsin mi?');">
                            <?= csrf_field() ?>
                            <button class="btn ok" type="submit">Geri Al</button>
                        </form>
                        <form method="post" action="/admin/devices/<?= e((string) $device['id']) ?>/purge" class="inline" onsubmit="return confirm('Bu cihaz kalici olarak silinsin mi? Bu islem geri alinamaz.');">
                            <?= csrf_field() ?>
                            <button class="btn danger" type="submit">Kalici Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$devices): ?>
                <tr><td colspan="6" class="muted">Silinen cihaz yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
