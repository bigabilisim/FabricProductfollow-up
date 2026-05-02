<?php
$statusLabels = [
    'done' => 'Yapildi',
    'not_done' => 'Yapilmadi',
    'rescheduled' => 'Baska zamana planlandi',
];
$status = $status ?: 'done';
?>
<div class="panel" style="max-width:680px;margin:0 auto;">
    <h1>Bakim Cevabi</h1>
    <p><strong><?= e($device['code']) ?></strong> kodlu cihaz icin durum bildirimi.</p>
    <p class="muted">Link gecerlilik suresi: <?= e($event['response_expires_at']) ?></p>

    <?php if ($event['status'] !== 'pending'): ?>
        <div class="flash">Bu bakim icin daha once cevap verildi: <?= e($event['status']) ?></div>
    <?php endif; ?>

    <form method="post" action="/maintenance/respond">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field">
            <label for="status">Durum</label>
            <select id="status" name="status">
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="rescheduled_at">Yeni tarih</label>
            <input id="rescheduled_at" name="rescheduled_at" type="date">
        </div>

        <div class="actions">
            <button class="btn primary" type="submit">Cevabi Kaydet</button>
        </div>
    </form>
</div>

