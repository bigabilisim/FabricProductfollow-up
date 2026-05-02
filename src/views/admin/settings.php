<?php

$mail = is_array($mail ?? null) ? $mail : [];
$backupRecipients = $mail['backup_recipients'] ?? [];
if (!is_array($backupRecipients)) {
    $backupRecipients = [];
}
$hasPassword = (string) ($mail['smtp_password'] ?? '') !== '';
?>
<div class="section-title">
    <div>
        <h1>Ayarlar</h1>
        <p class="muted">Mail gonderimi ve gunluk yedek alicilari.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin">Panele Don</a>
    </div>
</div>

<section class="panel">
    <form method="post" action="/admin/settings">
        <?= csrf_field() ?>

        <h2>SMTP Mail Ayarlari</h2>
        <div class="grid cols-3">
            <div class="field">
                <label for="mail_driver">Gonderim tipi</label>
                <select id="mail_driver" name="mail_driver">
                    <option value="smtp" <?= ($mail['driver'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                    <option value="mail" <?= ($mail['driver'] ?? '') === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
                </select>
            </div>
            <div class="field">
                <label for="smtp_host">SMTP sunucu</label>
                <input id="smtp_host" name="smtp_host" value="<?= e((string) ($mail['smtp_host'] ?? 'smtp.yandex.com.tr')) ?>">
            </div>
            <div class="field">
                <label for="smtp_port">Port</label>
                <input id="smtp_port" name="smtp_port" type="number" value="<?= e((string) ($mail['smtp_port'] ?? 465)) ?>">
            </div>
        </div>

        <div class="grid cols-3">
            <div class="field">
                <label for="smtp_encryption">Guvenlik</label>
                <select id="smtp_encryption" name="smtp_encryption">
                    <option value="ssl" <?= ($mail['smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="tls" <?= ($mail['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="none" <?= ($mail['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Yok</option>
                </select>
            </div>
            <div class="field">
                <label for="smtp_user">SMTP kullanici</label>
                <input id="smtp_user" name="smtp_user" type="email" value="<?= e((string) ($mail['smtp_user'] ?? 'bigaofis@alarmbigabilisim.com')) ?>">
            </div>
            <div class="field">
                <label for="smtp_password">SMTP parola</label>
                <input id="smtp_password" name="smtp_password" type="password" value="" placeholder="<?= $hasPassword ? 'Kayitli parola korunur' : 'Parola girin' ?>">
                <?php if ($hasPassword): ?>
                    <p class="muted field-note">SMTP parolasi kayitli.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid cols-2">
            <div class="field">
                <label for="from_email">Gonderen e-posta</label>
                <input id="from_email" name="from_email" type="email" value="<?= e((string) ($mail['from_email'] ?? 'bigaofis@alarmbigabilisim.com')) ?>">
            </div>
            <div class="field">
                <label for="from_name">Gonderen adi</label>
                <input id="from_name" name="from_name" value="<?= e((string) ($mail['from_name'] ?? 'Fabrika QR Bakim Takip')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="backup_recipients">Gunluk yedek mail alicilari</label>
            <textarea id="backup_recipients" name="backup_recipients"><?= e(implode(', ', $backupRecipients ?: ['bigaofis@alarmbigabilisim.com'])) ?></textarea>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit">Ayarlari Kaydet</button>
        </div>
    </form>
</section>
