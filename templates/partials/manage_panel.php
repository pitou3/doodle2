<?php
/**
 * Panneau de gestion (créateur ou administrateur), affiché au-dessus du sondage.
 * Variables de poll.php + :
 * @var ?string $adminUrl  lien d'administration du créateur (null pour un admin du site)
 * @var bool $isSiteAdmin
 * @var bool $created
 * @var ?string $mailStatus none|sent|failed
 * @var array $settingsErrors
 * @var ?array $settingsOld
 * @var array $optionsErrors
 * @var ?array $optionsOld
 */
$settingsValues = $settingsOld ?? [
    'author_name' => $poll['author_name'],
    'author_email' => (string) $poll['author_email'],
    'title' => $poll['title'],
    'description' => (string) $poll['description'],
    'edit_policy' => $poll['edit_policy'],
    'allow_maybe' => $poll['allow_maybe'] ? '1' : '',
    'deadline' => $poll['deadline'] !== null ? (new DateTimeImmutable($poll['deadline']))->format('Y-m-d\TH:i') : '',
    'closed' => $poll['closed_at'] !== null ? '1' : '',
];
$existingOld = is_array($optionsOld['existing'] ?? null) ? $optionsOld['existing'] : [];
?>
<section class="manage">
    <?php if ($created): ?>
        <div class="alert alert-success created-box">
            <h2><?= e(t('manage.created.title')) ?></h2>
            <p><?= e(t('manage.created.text')) ?></p>
            <?php if ($mailStatus === 'sent'): ?>
                <p><?= e(t('manage.created.mail_sent', ['email' => $poll['author_email']])) ?></p>
            <?php elseif ($mailStatus === 'failed'): ?>
                <p class="text-danger"><?= e(t('manage.created.mail_failed')) ?></p>
            <?php else: ?>
                <p class="text-warning"><strong><?= e(t('manage.created.no_mail')) ?></strong></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card manage-card">
        <div class="manage-title">
            <h2><?= e(t($isSiteAdmin ? 'manage.title_admin' : 'manage.title')) ?></h2>
            <?php if ($isSiteAdmin): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin')) ?>">← <?= e(t('admin.dashboard.title')) ?></a>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="share-public"><?= e(t('manage.public_link')) ?></label>
            <p class="hint"><?= e(t('manage.public_link_hint')) ?></p>
            <div class="copy-field">
                <input type="text" readonly id="share-public" value="<?= e($publicUrl) ?>">
                <button type="button" class="btn btn-secondary" data-copy="share-public" data-copied="<?= e(t('common.copied')) ?>"><?= e(t('common.copy')) ?></button>
                <a class="btn btn-ghost" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e(t('common.open')) ?></a>
            </div>
        </div>
        <?php if ($adminUrl !== null): ?>
            <div class="field">
                <label for="share-admin"><?= e(t('manage.admin_link')) ?></label>
                <p class="hint text-danger"><?= e(t('manage.admin_link_hint')) ?></p>
                <div class="copy-field">
                    <input type="text" readonly id="share-admin" value="<?= e($adminUrl) ?>">
                    <button type="button" class="btn btn-secondary" data-copy="share-admin" data-copied="<?= e(t('common.copied')) ?>"><?= e(t('common.copy')) ?></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$isOpen || $poll['ends_at'] === null): ?>
            <p class="hint"><?= e(t($isOpen ? 'manage.retention_inactive' : 'manage.retention_closed', [
                'date' => $i18n->formatDate($purgeDate->format('Y-m-d')),
            ])) ?></p>
        <?php endif; ?>

        <details class="panel" id="settings" <?= $settingsErrors ? 'open' : '' ?>>
            <summary><?= e(t('manage.settings')) ?></summary>
            <form method="post" action="<?= e(url($base . '/settings')) ?>" class="form">
                <?= csrf_field() ?>
                <?php if ($settingsErrors): ?><div class="alert alert-error"><?= e(t('form.has_errors')) ?></div><?php endif; ?>
                <?= $this->partial('partials/poll_fields', ['values' => $settingsValues, 'errors' => $settingsErrors, 'idPrefix' => 's-']) ?>
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="closed" value="1" <?= !empty($settingsValues['closed']) ? 'checked' : '' ?>>
                        <span><?= e(t('manage.closed')) ?></span>
                    </label>
                    <p class="hint"><?= e(t('manage.closed_hint')) ?></p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= e(t('common.save')) ?></button>
                </div>
            </form>
        </details>

        <details class="panel" id="options" <?= $optionsErrors ? 'open' : '' ?>>
            <summary><?= e(t($isDate ? 'manage.edit_dates' : 'manage.edit_options')) ?></summary>
            <form method="post" action="<?= e(url($base . '/options')) ?>" class="form">
                <?= csrf_field() ?>
                <?php foreach ($optionsErrors as $key): ?><div class="alert alert-error"><?= e(t($key)) ?></div><?php endforeach; ?>
                <?php if ($votes): ?><p class="hint text-warning"><?= e(t('manage.options_votes_warning')) ?></p><?php endif; ?>

                <div class="row-list">
                    <?php foreach ($options as $o): $oid = (int) $o['id']; $ov = is_array($existingOld[$oid] ?? null) ? $existingOld[$oid] : []; ?>
                        <div class="row-item <?= $isDate ? 'date-row' : 'option-row' ?> existing-row">
                            <?php if ($isDate): ?>
                                <input type="date" name="existing[<?= $oid ?>][date]" value="<?= e($ov['date'] ?? $o['option_date']) ?>" aria-label="<?= e(t('form.date')) ?>" required>
                                <input type="text" name="existing[<?= $oid ?>][label]" value="<?= e($ov['label'] ?? $o['label']) ?>" maxlength="50" placeholder="<?= e(t('form.time_placeholder')) ?>" aria-label="<?= e(t('form.times')) ?>">
                            <?php else: ?>
                                <input type="text" name="existing[<?= $oid ?>][label]" value="<?= e($ov['label'] ?? $o['label']) ?>" maxlength="255" aria-label="<?= e(t('form.option')) ?>" required>
                            <?php endif; ?>
                            <label class="checkbox delete-check" title="<?= e(t('form.remove')) ?>">
                                <input type="checkbox" name="existing[<?= $oid ?>][delete]" value="1" <?= !empty($ov['delete']) ? 'checked' : '' ?>>
                                <span><?= e(t('form.remove')) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="subhead"><?= e(t($isDate ? 'manage.add_dates' : 'manage.add_options')) ?></h3>
                <?php if ($isDate): ?>
                    <?= $this->partial('partials/date_rows', ['rows' => [[]], 'listId' => 'add-dates']) ?>
                <?php else: ?>
                    <?= $this->partial('partials/option_rows', ['rows' => [''], 'listId' => 'add-options']) ?>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= e(t('common.save')) ?></button>
                </div>
            </form>
        </details>

        <div class="manage-actions">
            <a class="btn btn-secondary" href="<?= e(url($base . '/export.csv')) ?>"><?= e(t('manage.export_csv')) ?></a>
            <form method="post" action="<?= e(url($base . '/delete')) ?>" class="inline" data-confirm="<?= e(t('manage.delete_confirm')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger"><?= e(t('manage.delete')) ?></button>
            </form>
        </div>
    </div>
</section>
