<div class="section-title">
    <div>
        <h1>Yedekler</h1>
        <p class="muted">Manuel yedek alin veya hazir yedegi tanimli alicilara mail olarak gonderin.</p>
    </div>
    <form method="post" action="/admin/backups/create">
        <?= csrf_field() ?>
        <button class="btn primary" type="submit">Yedek Al</button>
    </form>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Dosya</th>
                    <th>Boyut</th>
                    <th>Olusturma</th>
                    <th>Mail</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $backup): ?>
                <tr>
                    <td><?= e($backup['file_name']) ?></td>
                    <td><?= number_format(((int) $backup['file_size']) / 1024, 1) ?> KB</td>
                    <td><?= e($backup['created_at']) ?></td>
                    <td><?= e($backup['mailed_at'] ?: '-') ?></td>
                    <td>
                        <form method="post" action="/admin/backups/mail" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="backup_id" value="<?= e((string) $backup['id']) ?>">
                            <button class="btn" type="submit">Mail Gonder</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$backups): ?>
                <tr><td colspan="5" class="muted">Henuz yedek alinmadi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

