<?php /** @var array $admin @var string $active */ ?>
<div class="admin-nav">
    <nav>
        <a href="<?= e(url('/admin')) ?>" class="<?= $active === 'polls' ? 'active' : '' ?>"><?= e(t('admin.dashboard.title')) ?></a>
        <a href="<?= e(url('/admin/users')) ?>" class="<?= $active === 'users' ? 'active' : '' ?>"><?= e(t('admin.users.title')) ?></a>
    </nav>
    <form method="post" action="<?= e(url('/admin/logout')) ?>" class="inline">
        <?= csrf_field() ?>
        <span class="muted"><?= e($admin['username']) ?></span>
        <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('admin.logout')) ?></button>
    </form>
</div>
