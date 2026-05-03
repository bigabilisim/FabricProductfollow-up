<?php

$template = is_array($template ?? null) ? $template : [];
$css = trim((string) ($template['css'] ?? ''));
$html = (string) ($template['html'] ?? '');
?>
<div class="section-title">
    <div>
        <h1><?= e((string) ($template['name'] ?? 'Sablon')) ?></h1>
        <p class="muted"><code><?= e((string) ($template['slug'] ?? '')) ?></code> sablon onizlemesi.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/templates?type=<?= e((string) ($template['type'] ?? 'mail')) ?>">Listeye Don</a>
        <a class="btn primary" href="/admin/templates/<?= e((string) $template['id']) ?>/edit">Duzenle</a>
    </div>
</div>

<div class="panel">
    <?php if ((string) ($template['subject'] ?? '') !== ''): ?>
        <p><strong>Konu:</strong> <?= e((string) $template['subject']) ?></p>
    <?php endif; ?>
    <form class="actions template-preview-test" method="post" action="/admin/templates/<?= e((string) $template['id']) ?>/test-mail" onsubmit="return confirm('Bu onizleme test maili olarak gonderilsin mi?');">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e((string) ($template['type'] ?? 'mail')) ?>">
        <input type="hidden" name="slug" value="<?= e((string) ($template['slug'] ?? '')) ?>">
        <input type="hidden" name="name" value="<?= e((string) ($template['name'] ?? '')) ?>">
        <input type="hidden" name="subject" value="<?= e((string) ($template['subject'] ?? '')) ?>">
        <input type="hidden" name="html" value="<?= e($html) ?>">
        <input type="hidden" name="css" value="<?= e($css) ?>">
        <input type="hidden" name="project_json" value="<?= e((string) ($template['project_json'] ?? '')) ?>">
        <input type="hidden" name="active" value="<?= e((string) ($template['active'] ?? 1)) ?>">
        <input name="test_email" type="email" placeholder="Test mail adresi" required style="max-width:320px;">
        <button class="btn" type="submit">Test Mail Gonder</button>
    </form>
    <iframe class="template-preview-frame" title="Sablon onizleme" srcdoc="<?= e(($css !== '' ? '<style>' . $css . '</style>' : '') . $html) ?>"></iframe>
</div>
