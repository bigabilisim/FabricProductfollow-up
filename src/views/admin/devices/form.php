<?php
$isEdit = (bool) $device;
$value = static fn (string $key, mixed $default = ''): string => e((string) ($device[$key] ?? $default));
$identity = is_array($identity ?? null) ? $identity : [
    'company_code' => (string) ($device['company_code'] ?? 'ANA'),
    'country_code' => (string) ($device['country_code'] ?? 'TR'),
    'production_year' => (int) ($device['production_year'] ?? date('Y')),
    'machine_no' => (int) ($device['machine_no'] ?? 1),
    'code' => \App\Repositories\DeviceRepository::buildCode((string) ($device['company_code'] ?? 'ANA'), (string) ($device['country_code'] ?? 'TR'), (int) ($device['production_year'] ?? date('Y')), (int) ($device['machine_no'] ?? 1)),
];
?>
<div class="section-title">
    <div>
        <h1><?= $isEdit ? 'Cihaz Duzenle' : 'Yeni Cihaz' ?></h1>
        <p class="muted">Kod otomatik olarak firma-ulke-yil-makine no biciminde uretilir.</p>
    </div>
    <a class="btn" href="/admin/devices">Listeye Don</a>
</div>

<div class="panel">
    <form method="post" action="<?= e($action) ?>" data-device-form data-next-code-url="<?= e(app_path('/admin/devices/next-code')) ?>" data-device-id="<?= $isEdit ? e((string) $device['id']) : '' ?>">
        <?= csrf_field() ?>

        <div class="grid cols-4" style="grid-template-columns:repeat(4,minmax(0,1fr));">
            <div class="field">
                <label for="company_code">Firma kodu</label>
                <input id="company_code" name="company_code" maxlength="16" value="<?= e((string) $identity['company_code']) ?>" readonly aria-readonly="true" required>
            </div>
            <div class="field">
                <label for="country_code">Ulke kodu</label>
                <input id="country_code" name="country_code" maxlength="8" value="<?= e((string) $identity['country_code']) ?>" required>
            </div>
            <div class="field">
                <label for="production_year">Yil</label>
                <input id="production_year" name="production_year" type="number" value="<?= e((string) $identity['production_year']) ?>" required>
            </div>
            <div class="field">
                <label for="machine_no">Makine no</label>
                <input id="machine_no" name="machine_no" type="number" min="1" value="<?= e((string) $identity['machine_no']) ?>" readonly aria-readonly="true" required>
            </div>
        </div>
        <div class="field">
            <label for="device_code_preview">Makina kodu</label>
            <input id="device_code_preview" value="<?= e((string) $identity['code']) ?>" readonly aria-readonly="true">
            <p class="muted field-note">Ulke kodu veya yil degistiginde sistem siradaki makine numarasini ve kodu otomatik hesaplar.</p>
        </div>

        <div class="grid cols-3">
            <div class="field">
                <label for="serial_number">Seri numarasi</label>
                <input id="serial_number" name="serial_number" value="<?= $value('serial_number') ?>" required>
            </div>
            <div class="field">
                <label for="installed_at">Kurulum tarihi</label>
                <input id="installed_at" name="installed_at" type="date" value="<?= $value('installed_at', date('Y-m-d')) ?>" required>
            </div>
            <div class="field">
                <label for="maintenance_period_days">Bakim periyodu</label>
                <input id="maintenance_period_days" name="maintenance_period_days" type="number" min="1" value="<?= $value('maintenance_period_days', '180') ?>" required>
            </div>
        </div>

        <div class="grid cols-2">
            <div class="field">
                <label for="notify_before_days">Bildirim gunleri</label>
                <input id="notify_before_days" name="notify_before_days" value="<?= $value('notify_before_days', '30,14,7,3,1') ?>">
            </div>
            <div class="field">
                <label for="responsible_emails" class="label-with-info">
                    Yetkili mail adresleri
                    <span class="info-tip" tabindex="0" aria-label="Mail adresi ornegi">i
                        <span class="tooltip">Ornek: teknik@example.com, bakim@example.com</span>
                    </span>
                </label>
                <input id="responsible_emails" name="responsible_emails" value="<?= $value('responsible_emails') ?>" required>
                <p class="muted field-note">Birden fazla adresi virgul, noktali virgul veya bosluk ile ayirabilirsiniz.</p>
            </div>
        </div>

        <div class="field">
            <label for="hazard_note">Bakim yapilmazsa risk metni</label>
            <textarea id="hazard_note" name="hazard_note"><?= $value('hazard_note') ?></textarea>
        </div>

        <div class="field">
            <label for="notes">Cihaz notlari</label>
            <textarea id="notes" name="notes"><?= $value('notes') ?></textarea>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit"><?= $isEdit ? 'Guncelle' : 'Kaydet' ?></button>
            <?php if ($isEdit): ?>
                <a class="btn" href="/admin/devices/<?= e((string) $device['id']) ?>">Detay</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
(() => {
    const form = document.querySelector('[data-device-form]');
    if (!form) {
        return;
    }

    const company = document.getElementById('company_code');
    const country = document.getElementById('country_code');
    const year = document.getElementById('production_year');
    const machineNo = document.getElementById('machine_no');
    const codePreview = document.getElementById('device_code_preview');
    const endpoint = form.dataset.nextCodeUrl;
    let requestId = 0;

    const localPreview = () => {
        const parts = [
            (company.value || 'ANA').toUpperCase(),
            (country.value || 'TR').toUpperCase(),
            year.value || new Date().getFullYear(),
            machineNo.value || '1'
        ];
        codePreview.value = parts.join('-');
    };

    const refreshIdentity = async () => {
        if (!endpoint) {
            localPreview();
            return;
        }

        const currentRequest = ++requestId;
        const params = new URLSearchParams({
            country_code: country.value || 'TR',
            production_year: year.value || String(new Date().getFullYear()),
        });
        if (form.dataset.deviceId) {
            params.set('device_id', form.dataset.deviceId);
        }

        try {
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            if (currentRequest !== requestId || !data.ok || !data.identity) {
                return;
            }

            company.value = data.identity.company_code || 'ANA';
            country.value = data.identity.country_code || country.value;
            year.value = data.identity.production_year || year.value;
            machineNo.value = data.identity.machine_no || '1';
            codePreview.value = data.identity.code || '';
            localPreview();
        } catch (error) {
            localPreview();
        }
    };

    country.addEventListener('input', refreshIdentity);
    year.addEventListener('input', refreshIdentity);
    country.addEventListener('change', refreshIdentity);
    year.addEventListener('change', refreshIdentity);
    localPreview();
})();
</script>
