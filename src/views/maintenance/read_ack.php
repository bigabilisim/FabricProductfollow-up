<div class="panel" style="max-width:620px;margin:0 auto;">
    <?php if ($event): ?>
        <h1>Okundu Onayi Alindi</h1>
        <p><strong><?= e($event['code']) ?></strong> icin risk bildiriminin okundugu kaydedildi.</p>
    <?php else: ?>
        <h1>Link bulunamadi</h1>
        <p class="muted">Okundu onayi linki gecersiz veya daha once kaldirilmis olabilir.</p>
    <?php endif; ?>
</div>

