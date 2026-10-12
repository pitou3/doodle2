<div class="narrow">
    <form method="post" action="<?= e(url('/admin/setup')) ?>" class="card form">
        <h1><?= e(t('admin.setup.title')) ?></h1>
        <p class="hint"><?= e(t('admin.setup.intro')) ?></p>
        <?= csrf_field() ?>
        <div class="field">
            <label for="setup_token"><?= e(t('admin.setup.token')) ?></label>
            <input type="password" id="setup_token" name="setup_token" required autocomplete="off">
        </div>
        <div class="field">
            <label for="username"><?= e(t('admin.username')) ?></label>
            <input type="text" id="username" name="username" autocomplete="username" maxlength="60">
        </div>
        <div class="field">
            <label for="password"><?= e(t('admin.password')) ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="10">
        </div>
        <div class="field">
            <label for="password_confirm"><?= e(t('admin.password_confirm')) ?></label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="10">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(t('admin.setup.submit')) ?></button>
        </div>
    </form>
</div>
