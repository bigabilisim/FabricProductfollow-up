<div class="panel" style="max-width:620px;margin:0 auto;">
    <h1><?= e($device['code']) ?></h1>
    <p class="muted">Bu cihaz detaylarini gormek icin tanimli mail adreslerine dogrulama kodu gonderilir.</p>

    <form method="post" action="/scan/request-code" class="actions" style="margin-bottom:18px;">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn primary" type="submit">Kodu Mail Gonder</button>
    </form>

    <form method="post" action="/scan/verify-code">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
            <label for="code">Dogrulama kodu</label>
            <input id="code" name="code" inputmode="numeric" maxlength="6" placeholder="123456" required>
        </div>
        <button class="btn" type="submit">Cihaz Detaylarini Ac</button>
    </form>
</div>

