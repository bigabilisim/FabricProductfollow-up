<?php

$template = is_array($template ?? null) ? $template : [];
$type = (string) ($type ?? ($template['type'] ?? 'mail'));
$isMail = $type === 'mail';
$isEdit = !empty($template['id']);
$action = $isEdit ? '/admin/templates/' . (int) $template['id'] : '/admin/templates';
$projectJson = (string) ($template['project_json'] ?? '');
$html = (string) ($template['html'] ?? '');
$css = (string) ($template['css'] ?? '');
?>
<link rel="stylesheet" href="/assets/vendor/grapesjs/grapes.min.css">

<div class="section-title">
    <div>
        <h1><?= $isEdit ? 'Sablon Duzenle' : 'Yeni Sablon' ?></h1>
        <p class="muted"><?= $isMail ? 'Mail tasarimini GrapesJS ile duzenleyin.' : 'Rapor tasarimini GrapesJS ile duzenleyin.' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/templates?type=<?= e($type) ?>">Listeye Don</a>
        <?php if ($isEdit): ?>
            <a class="btn" href="/admin/templates/<?= e((string) $template['id']) ?>/preview">Onizle</a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-template-form>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="html" id="template-html" value="<?= e($html) ?>">
    <input type="hidden" name="css" id="template-css" value="<?= e($css) ?>">
    <input type="hidden" name="project_json" id="template-project" value="<?= e($projectJson) ?>">

    <section class="panel template-meta">
        <div class="grid <?= $isMail ? 'cols-3' : 'cols-2' ?>">
            <div class="field">
                <label for="name">Sablon adi</label>
                <input id="name" name="name" value="<?= e((string) ($template['name'] ?? '')) ?>" required>
            </div>
            <div class="field">
                <label for="slug">Sablon kodu</label>
                <input id="slug" name="slug" value="<?= e((string) ($template['slug'] ?? '')) ?>" required>
            </div>
            <?php if ($isMail): ?>
                <div class="field">
                    <label for="subject">Mail konusu</label>
                    <input id="subject" name="subject" value="<?= e((string) ($template['subject'] ?? '')) ?>">
                </div>
            <?php endif; ?>
        </div>
        <label class="checkline">
            <input name="active" type="checkbox" value="1" <?= (int) ($template['active'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Aktif</span>
        </label>
        <?php if (!empty($template['is_system'])): ?>
            <input type="hidden" name="is_system" value="1">
            <p class="muted field-note">Bu bir sistem sablonudur; silinmez ama duzenlenebilir.</p>
        <?php endif; ?>
        <?php if ($isEdit): ?>
            <div class="template-test-mail">
                <div class="field">
                    <label for="test_email">Test mail adresi</label>
                    <input id="test_email" name="test_email" type="email" placeholder="ornek@firma.com">
                    <p class="muted field-note">Kaydetmeden once mevcut editor icerigiyle test maili gonderebilirsiniz.</p>
                </div>
                <button
                    class="btn"
                    type="submit"
                    formaction="/admin/templates/<?= e((string) $template['id']) ?>/test-mail"
                    formmethod="post"
                    onclick="return confirm('Bu sablon test maili olarak gonderilsin mi?');"
                >Test Mail Gonder</button>
            </div>
        <?php endif; ?>
    </section>

    <section class="template-builder-wrap">
        <div class="template-toolbar">
            <div class="muted">
                Kullanilabilir degiskenler:
                <?php if ($isMail): ?>
                    <code>{{device_code}}</code> <code>{{maintenance_date}}</code> <code>{{days_left}}</code> <code>{{done_url}}</code> <code>{{ack_url}}</code>
                <?php else: ?>
                    <code>{{device_code}}</code> <code>{{status}}</code> <code>{{report_date}}</code>
                <?php endif; ?>
            </div>
            <button class="btn primary" type="submit">Sablonu Kaydet</button>
        </div>
        <div
            id="template-editor"
            data-template-html="<?= e($html) ?>"
            data-template-css="<?= e($css) ?>"
            data-template-project="<?= e($projectJson) ?>"
            data-template-type="<?= e($type) ?>"
        ></div>
    </section>
</form>

<script src="/assets/vendor/grapesjs/grapes.min.js"></script>
<script src="/assets/template-editor.js"></script>
