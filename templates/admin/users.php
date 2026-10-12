<?php
/**
 * @var array $admin
 * @var list<array> $users
 */
$i18n = App\App::get()->i18n;
?>
<?= $this->partial('admin/_nav', ['admin' => $admin, 'active' => 'users']) ?>

<div class="page-head">
    <h1><?= e(t('admin.users.title')) ?></h1>
</div>

<div class="card flush table-wrap">
    <table class="admin-table">
        <thead>
        <tr>
            <th><?= e(t('admin.username')) ?></th>
            <th><?= e(t('admin.users.created')) ?></th>
            <th><?= e(t('admin.users.last_login')) ?></th>
            <th><span class="sr-only"><?= e(t('results.actions')) ?></span></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td data-label="<?= e(t('admin.username')) ?>"><strong><?= e($u['username']) ?></strong></td>
                <td data-label="<?= e(t('admin.users.created')) ?>"><?= e($i18n->formatDate($u['created_at'])) ?></td>
                <td data-label="<?= e(t('admin.users.last_login')) ?>"><?= $u['last_login_at'] ? e($i18n->formatDateTime($u['last_login_at'])) : '–' ?></td>
                <td class="row-actions">
                    <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                        <form method="post" action="<?= e(url('/admin/users/' . (int) $u['id'] . '/delete')) ?>" class="inline"
                              data-confirm="<?= e(t('admin.users.delete_confirm', ['name' => $u['username']])) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm"><?= e(t('common.delete')) ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="grid-2">
    <form method="post" action="<?= e(url('/admin/users')) ?>" class="card form">
        <h2><?= e(t('admin.users.add')) ?></h2>
        <?= csrf_field() ?>
        <div class="field">
            <label for="new-username"><?= e(t('admin.username')) ?></label>
            <input type="text" id="new-username" name="username" required maxlength="60" autocomplete="off">
        </div>
        <div class="field">
            <label for="new-password"><?= e(t('admin.password')) ?></label>
            <input type="password" id="new-password" name="password" required minlength="10" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="new-password2"><?= e(t('admin.password_confirm')) ?></label>
            <input type="password" id="new-password2" name="password_confirm" required minlength="10" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary"><?= e(t('admin.users.add')) ?></button>
    </form>

    <form method="post" action="<?= e(url('/admin/password')) ?>" class="card form">
        <h2><?= e(t('admin.password.change')) ?></h2>
        <?= csrf_field() ?>
        <div class="field">
            <label for="cur-password"><?= e(t('admin.password.current')) ?></label>
            <input type="password" id="cur-password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
            <label for="chg-password"><?= e(t('admin.password.new')) ?></label>
            <input type="password" id="chg-password" name="password" required minlength="10" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="chg-password2"><?= e(t('admin.password_confirm')) ?></label>
            <input type="password" id="chg-password2" name="password_confirm" required minlength="10" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary"><?= e(t('admin.password.change')) ?></button>
    </form>
</div>
