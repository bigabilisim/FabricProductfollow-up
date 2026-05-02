<div class="panel">
    <div class="stepbar" aria-hidden="true">
        <span class="active"></span>
        <span class="active"></span>
        <span class="active"></span>
    </div>
    <h1>Kurulum Sihirbazi</h1>
    <p class="muted">Surum 1.0V - Kurulum icin MariaDB ve admin giris bilgilerini girmeniz yeterlidir.</p>

    <form method="post" action="/install" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="base_path" value="<?= e($defaultBasePath ?? '') ?>">
        <input type="hidden" name="site_name" value="Fabrika QR Bakim Takip">
        <input type="hidden" name="base_url" value="<?= e($defaultBaseUrl) ?>">
        <input type="hidden" name="timezone" value="Europe/Istanbul">
        <input type="hidden" name="maintenance_warning_days" value="30,14,7,3,1">
        <input type="hidden" name="install_mode" value="fresh">
        <input type="hidden" name="mail_driver" value="smtp">
        <input type="hidden" name="from_email" value="bigaofis@alarmbigabilisim.com">
        <input type="hidden" name="from_name" value="Fabrika QR Bakim Takip">
        <input type="hidden" name="smtp_host" value="smtp.yandex.com.tr">
        <input type="hidden" name="smtp_port" value="465">
        <input type="hidden" name="smtp_user" value="bigaofis@alarmbigabilisim.com">
        <input type="hidden" name="smtp_encryption" value="ssl">
        <input type="hidden" name="backup_recipients" value="bigaofis@alarmbigabilisim.com">

        <h2>MariaDB</h2>
        <div class="grid cols-3">
            <div class="field">
                <label for="db_host">Host</label>
                <input id="db_host" name="db_host" value="127.0.0.1" required>
            </div>
            <div class="field">
                <label for="db_port">Port</label>
                <input id="db_port" name="db_port" type="number" value="3306" required>
            </div>
            <div class="field">
                <label for="db_name">Veritabani</label>
                <input id="db_name" name="db_name" value="factory_qr" required>
            </div>
        </div>
        <div class="grid cols-2">
            <div class="field">
                <label for="db_user">Kullanici</label>
                <input id="db_user" name="db_user" value="root" required>
            </div>
            <div class="field">
                <label for="db_password">Parola</label>
                <input id="db_password" name="db_password" type="password" value="">
            </div>
        </div>
        <div class="actions" style="margin:-4px 0 18px;">
            <button class="btn" type="button" id="db-test-button" data-test-url="<?= e(app_path('/install/test-db')) ?>">SQL Baglantisini Test Et</button>
            <span id="db-test-result" class="db-test-result muted" role="status"></span>
        </div>

        <h2>Admin</h2>
        <div class="grid cols-3">
            <div class="field">
                <label for="admin_name">Ad soyad</label>
                <input id="admin_name" name="admin_name" value="">
            </div>
            <div class="field">
                <label for="admin_email">E-posta</label>
                <input id="admin_email" name="admin_email" type="email" required>
            </div>
            <div class="field">
                <label for="admin_password">Parola</label>
                <input id="admin_password" name="admin_password" type="password" minlength="8" required>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn primary">Kurulumu Baslat</button>
        </div>
    </form>
</div>

<script>
(() => {
    const button = document.getElementById('db-test-button');
    const result = document.getElementById('db-test-result');
    if (!button || !result) {
        return;
    }

    button.addEventListener('click', async () => {
        const form = button.closest('form');
        const payload = new FormData();
        ['_csrf', 'db_host', 'db_port', 'db_name', 'db_user', 'db_password'].forEach((name) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field) {
                payload.append(name, field.value);
            }
        });

        button.disabled = true;
        result.className = 'db-test-result muted';
        result.textContent = 'Baglanti test ediliyor...';

        try {
            const response = await fetch(button.dataset.testUrl, {
                method: 'POST',
                body: payload,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            result.className = 'db-test-result ' + (data.ok ? 'ok' : 'error');
            result.textContent = data.message || (data.ok ? 'Baglanti basarili.' : 'Baglanti basarisiz.');
        } catch (error) {
            result.className = 'db-test-result error';
            result.textContent = 'Test istegi tamamlanamadi.';
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
