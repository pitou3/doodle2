<?php
/**
 * @var array $admin
 * @var list<array> $polls
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $q
 * @var string $status
 * @var App\Service\PollService $service
 * @var int $retention
 */
$i18n = App\App::get()->i18n;
$pageUrl = static fn (int $n) => url('/admin') . '?' . http_build_query(['q' => $q, 'status' => $status, 'page' => $n]);
?>
<?= $this->partial('admin/_nav', ['admin' => $admin, 'active' => 'polls']) ?>

<div class="page-head">
    <h1><?= e(t('admin.dashboard.title')) ?> <span class="count">(<?= $total ?>)</span></h1>
</div>

<form method="get" action="<?= e(url('/admin')) ?>" class="card filters">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('admin.search_placeholder')) ?>" aria-label="<?= e(t('admin.search_placeholder')) ?>">
    <select name="status" aria-label="<?= e(t('admin.status')) ?>">
        <?php foreach (['all', 'open', 'archived'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(t('admin.filter.' . $s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary"><?= e(t('admin.filter')) ?></button>
</form>

<?php if (!$polls): ?>
    <p class="card muted"><?= e(t('admin.no_polls')) ?></p>
<?php else: ?>
    <div class="table-wrap card flush">
        <table class="admin-table">
            <thead>
            <tr>
                <th><?= e(t('admin.col.title')) ?></th>
                <th><?= e(t('admin.col.author')) ?></th>
                <th><?= e(t('admin.col.created')) ?></th>
                <th class="num"><?= e(t('admin.col.votes')) ?></th>
                <th><?= e(t('admin.col.status')) ?></th>
                <th><?= e(t('admin.col.purge')) ?></th>
                <th><span class="sr-only"><?= e(t('results.actions')) ?></span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($polls as $p): $open = $service->isOpen($p); ?>
                <tr>
                    <td data-label="<?= e(t('admin.col.title')) ?>">
                        <a href="<?= e(url('/admin/poll/' . $p['public_id'])) ?>"><strong><?= e($p['title']) ?></strong></a>
                        <br><small class="muted"><?= e(t($p['type'] === 'date' ? 'poll.type.date' : 'poll.type.classic')) ?></small>
                    </td>
                    <td data-label="<?= e(t('admin.col.author')) ?>">
                        <?= e($p['author_name']) ?>
                        <?php if ($p['author_email']): ?><br><small class="muted"><?= e($p['author_email']) ?></small><?php endif; ?>
                    </td>
                    <td data-label="<?= e(t('admin.col.created')) ?>"><?= e($i18n->formatDate($p['created_at'])) ?></td>
                    <td data-label="<?= e(t('admin.col.votes')) ?>" class="num"><?= (int) $p['vote_count'] ?></td>
                    <td data-label="<?= e(t('admin.col.status')) ?>">
                        <?php if ($open): ?>
                            <span class="badge badge-open"><?= e(t('poll.status.open')) ?></span>
                            <?php if ($p['ends_at']): ?><br><small class="muted"><?= e(t('admin.until', ['date' => $i18n->formatDate($p['ends_at'])])) ?></small><?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-closed"><?= e(t('poll.status.archived')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= e(t('admin.col.purge')) ?>"><?= e($i18n->formatDate($service->purgeDate($p)->format('Y-m-d'))) ?></td>
                    <td class="row-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/p/' . $p['public_id'])) ?>" target="_blank" rel="noopener"><?= e(t('admin.view')) ?></a>
                        <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/poll/' . $p['public_id'])) ?>"><?= e(t('admin.manage')) ?></a>
                        <form method="post" action="<?= e(url('/admin/poll/' . $p['public_id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(t('manage.delete_confirm')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm"><?= e(t('common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($pageUrl($page - 1)) ?>">←</a><?php endif; ?>
            <span><?= e(t('admin.page', ['page' => $page, 'pages' => $pages])) ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e($pageUrl($page + 1)) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<div class="card">
    <h2><?= e(t('admin.retention.title')) ?></h2>
    <p class="hint"><?= e(t('admin.retention.text', ['months' => $retention])) ?></p>
    <form method="post" action="<?= e(url('/admin/purge')) ?>" class="inline" data-confirm="<?= e(t('admin.purge_confirm')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary"><?= e(t('admin.purge_now')) ?></button>
    </form>
</div>
