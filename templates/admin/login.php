<?php /** @var bool $setupAvailable */ ?>
<div class="narrow">
    <form method="post" action="<?= e(url('/admin/login')) ?>" class="card form">
        <h1><?= e(t('admin.login.title')) ?></h1>
        <?= csrf_field() ?>
        <div class="field">
            <label for="username"><?= e(t('admin.username')) ?></label>
            <input type="text" id="username" name="username" required autocomplete="username" autofocus>
        </div>
        <div class="field">
            <label for="password"><?= e(t('admin.password')) ?></label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(t('admin.login.submit')) ?></button>
        </div>
        <?php if ($setupAvailable): ?>
            <p class="hint"><a href="<?= e(url('/admin/setup')) ?>"><?= e(t('admin.setup.link')) ?></a></p>
        <?php endif; ?>
    </form>
</div>
