<div class="panel" style="max-width:440px;margin:0 auto;">
    <h1>Admin Giris</h1>
    <form method="post" action="/login">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">E-posta</label>
            <input id="email" name="email" type="email" required autofocus>
        </div>
        <div class="field">
            <label for="password">Parola</label>
            <input id="password" name="password" type="password" required>
        </div>
        <button class="btn primary" type="submit">Giris Yap</button>
    </form>
</div>

