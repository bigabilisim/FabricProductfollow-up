<div class="panel" style="max-width:620px;margin:0 auto;">
    <h1><?= e($device['code']) ?></h1>
    <p class="muted">Bu cihaz detaylarini gormek icin yetkili mail adreslerine 48 saatlik giris linki gonderilir.</p>
    <p class="muted">QR kod sabittir; her talepte yeni ve sureli bir link uretilir.</p>

    <form method="post" action="/scan/request-link" class="actions" style="margin-bottom:18px;">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn primary" type="submit">Yetkiliye QR Gonder</button>
    </form>
</div>
