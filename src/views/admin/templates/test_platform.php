<?php

$templates = is_array($templates ?? null) ? $templates : [];
$selectedTemplate = is_array($selectedTemplate ?? null) ? $selectedTemplate : null;
$rendered = is_array($rendered ?? null) ? $rendered : null;
$variables = is_array($variables ?? null) ? $variables : [];
$selectedId = (int) ($selectedTemplate['id'] ?? 0);
$previewHtml = $rendered ? (string) ($rendered['html'] ?? '') : '<p>Mail sablonu secin.</p>';
?>
<div class="section-title">
    <div>
        <h1>Mail Test Platformu</h1>
        <p class="muted">Mail sablonlarini ornek cihaz verileriyle render edip test adreslerine gonderin.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/templates?type=mail">Mail Sablonlari</a>
        <a class="btn primary" href="/admin/templates/create?type=mail">Yeni Mail Sablonu</a>
    </div>
</div>

<div class="template-test-grid">
    <section class="panel">
        <h2>Test Gonderimi</h2>
        <form class="template-select-form" method="get" action="/admin/templates/test-platform">
            <div class="field">
                <label for="template_id">Sablon</label>
                <select id="template_id" name="template_id" <?= $templates === [] ? 'disabled' : '' ?>>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?= e((string) $template['id']) ?>" <?= (int) $template['id'] === $selectedId ? 'selected' : '' ?>>
                            <?= e((string) $template['name']) ?> (<?= e((string) $template['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit" <?= $templates === [] ? 'disabled' : '' ?>>Sablonu Sec</button>
        </form>

        <?php if ($selectedTemplate): ?>
            <form method="post" action="/admin/templates/test-platform" onsubmit="return confirm('Test maili girilen adreslere gonderilsin mi?');">
                <?= csrf_field() ?>
                <input type="hidden" name="template_id" value="<?= e((string) $selectedId) ?>">
                <div class="field">
                    <label for="test_emails">Test mail adresleri</label>
                    <textarea
                        id="test_emails"
                        name="test_emails"
                        placeholder="ornek@firma.com&#10;destek@firma.com"
                        required
                    ></textarea>
                    <p class="muted field-note">Virgul, noktali virgul, bosluk veya alt satir ile ayirabilirsiniz. Tek testte en fazla 20 adres kullanilir.</p>
                </div>
                <button class="btn primary" type="submit">Test Mail Gonder</button>
            </form>
        <?php else: ?>
            <p class="muted">Test edilecek mail sablonu bulunamadi.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Onizleme</h2>
        <?php if ($rendered): ?>
            <p class="template-preview-subject"><strong>Konu:</strong> <?= e((string) ($rendered['subject'] ?? '')) ?></p>
        <?php endif; ?>
        <iframe class="template-preview-frame template-test-preview-frame" title="Mail test onizleme" srcdoc="<?= e($previewHtml) ?>"></iframe>
    </section>
</div>

<section class="panel template-sample-vars">
    <h2>Ornek Degiskenler</h2>
    <div class="template-var-list">
        <?php foreach ($variables as $key => $value): ?>
            <code>{{<?= e((string) $key) ?>}} = <?= e((string) $value) ?></code>
        <?php endforeach; ?>
    </div>
</section>
