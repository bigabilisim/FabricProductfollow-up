<?php

$type = (string) ($type ?? 'mail');
$isMail = $type === 'mail';
$titleText = $isMail ? 'Mail Sablonlari' : 'Rapor Sablonlari';
?>
<div class="section-title">
    <div>
        <h1><?= e($titleText) ?></h1>
        <p class="muted"><?= $isMail ? 'Bakim ve sistem mailleri icin GrapesJS ile duzenlenebilir HTML sablonlari.' : 'Rapor ciktilari icin GrapesJS ile duzenlenebilir tasarim sablonlari.' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/templates?type=mail">Mail</a>
        <a class="btn" href="/admin/templates?type=report">Rapor</a>
        <?php if ($isMail): ?>
            <a class="btn" href="/admin/templates/test-platform">Test Platformu</a>
        <?php endif; ?>
        <a class="btn primary" href="/admin/templates/create?type=<?= e($type) ?>">Yeni Sablon</a>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ad</th>
                    <th>Kod</th>
                    <?php if ($isMail): ?>
                        <th>Konu</th>
                    <?php endif; ?>
                    <th>Durum</th>
                    <th>Tip</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $template): ?>
                <tr>
                    <td><?= e((string) $template['name']) ?></td>
                    <td><code><?= e((string) $template['slug']) ?></code></td>
                    <?php if ($isMail): ?>
                        <td><?= e((string) ($template['subject'] ?? '-')) ?></td>
                    <?php endif; ?>
                    <td><?= (int) $template['active'] === 1 ? '<span class="badge">Aktif</span>' : '<span class="badge">Pasif</span>' ?></td>
                    <td><?= (int) $template['is_system'] === 1 ? 'Sistem' : 'Ozel' ?></td>
                    <td class="actions">
                        <a class="btn" href="/admin/templates/<?= e((string) $template['id']) ?>/edit">Duzenle</a>
                        <a class="btn" href="/admin/templates/<?= e((string) $template['id']) ?>/preview">Onizle</a>
                        <?php if ((int) $template['is_system'] !== 1): ?>
                            <form method="post" action="/admin/templates/<?= e((string) $template['id']) ?>/delete" class="inline" onsubmit="return confirm('Bu sablon silinsin mi?');">
                                <?= csrf_field() ?>
                                <button class="btn danger" type="submit">Sil</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$templates): ?>
                <tr><td colspan="<?= $isMail ? 6 : 5 ?>" class="muted">Sablon bulunamadi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
