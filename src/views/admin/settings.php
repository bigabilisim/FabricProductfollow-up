<?php

$app = is_array($app ?? null) ? $app : [];
$mail = is_array($mail ?? null) ? $mail : [];
$backupRecipients = $mail['backup_recipients'] ?? [];
if (!is_array($backupRecipients)) {
    $backupRecipients = [];
}
$hasPassword = (string) ($mail['smtp_password'] ?? '') !== '';
$logoPath = (string) ($app['logo_path'] ?? '');
$webPushAvailable = (bool) ($webPushAvailable ?? false);
$webPushSubscriptionCount = (int) ($webPushSubscriptionCount ?? 0);
$maintenanceWarningDays = \App\Repositories\DeviceRepository::normalizeDayList($app['maintenance_warning_days'] ?? [30, 14, 7, 3, 1]) ?: [30, 14, 7, 3, 1];
?>
<div class="section-title">
    <div>
        <h1>Ayarlar</h1>
        <p class="muted">Uygulama logosu, site ikonu, mail gonderimi ve gunluk yedek alicilari.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin">Panele Don</a>
    </div>
</div>

<section class="panel">
    <form method="post" action="/admin/settings" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <h2>Logo</h2>
        <div class="settings-logo-row">
            <div class="logo-preview">
                <?php if ($logoPath !== ''): ?>
                    <img src="<?= e(app_path($logoPath)) ?>" alt="Site logosu">
                <?php else: ?>
                    <span>Logo Yok</span>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="site_logo">Logo dosyasi</label>
                <input id="site_logo" name="site_logo" type="file" accept="image/png,image/jpeg,image/webp">
                <p class="muted field-note">PNG, JPG veya WebP yukleyin. En fazla 2 MB. Bu logo ust barda ve tarayici sekme ikonunda kullanilir.</p>
                <?php if ($logoPath !== ''): ?>
                    <label class="checkline">
                        <input name="remove_logo" type="checkbox" value="1">
                        <span>Mevcut logoyu kaldir</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <h2>Bakim Bildirim Gunleri</h2>
        <div class="field">
            <label for="maintenance_warning_day_input">Varsayilan bildirim gunleri</label>
            <div class="day-picker" data-day-picker>
                <input
                    id="maintenance_warning_days"
                    name="maintenance_warning_days"
                    type="hidden"
                    value="<?= e(implode(',', $maintenanceWarningDays)) ?>"
                    data-day-values
                >
                <div class="day-chips" data-day-chips aria-live="polite"></div>
                <div class="day-add-row">
                    <input id="maintenance_warning_day_input" type="number" min="1" step="1" placeholder="Gun" data-day-input>
                    <button class="btn" type="button" data-day-add>+ Gun Ekle</button>
                </div>
            </div>
            <p class="muted field-note">Cihaz ekleme ve duzenleme ekraninda hizli secenek olarak bu gunler gosterilir.</p>
        </div>

        <h2>Web Push Bildirimleri</h2>
        <div class="web-push-panel" data-web-push-panel>
            <div>
                <p class="muted">Bu tarayicida web push izni verildiginde bakim hatirlatmalari ve kritik durumlar anlik bildirim olarak gelir.</p>
                <p class="muted field-note">Bu kullanici icin aktif abonelik: <strong data-web-push-count><?= $webPushSubscriptionCount ?></strong></p>
                <?php if (!$webPushAvailable): ?>
                    <p class="flash error">Web Push kutuphanesi bulunamadi. Sunucuda <code>composer install</code> calistirilmalidir.</p>
                <?php endif; ?>
                <p class="web-push-status muted" data-web-push-status role="status"></p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" data-web-push-enable <?= $webPushAvailable ? '' : 'disabled' ?>>Bildirimleri Ac</button>
                <button class="btn" type="button" data-web-push-test <?= $webPushAvailable ? '' : 'disabled' ?>>Test Bildirimi Gonder</button>
            </div>
        </div>

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
