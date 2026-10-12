<?php
/**
 * Lignes « date + horaires » pour ajouter des dates.
 * @var list<array{date?: string, times?: string}> $rows
 * @var string $listId
 */
?>
<div class="row-list" id="<?= e($listId) ?>">
    <div class="row-head date-row" aria-hidden="true">
        <span><?= e(t('form.date')) ?></span>
        <span><?= e(t('form.times')) ?></span>
        <span></span>
    </div>
    <?php foreach ($rows as $i => $row): ?>
        <div class="row-item date-row">
            <input type="date" name="dates[<?= e((string) $i) ?>][date]" value="<?= e(is_string($row['date'] ?? null) ? $row['date'] : '') ?>" aria-label="<?= e(t('form.date')) ?>">
            <input type="text" name="dates[<?= e((string) $i) ?>][times]" value="<?= e(is_string($row['times'] ?? null) ? $row['times'] : '') ?>"
                   placeholder="<?= e(t('form.times_placeholder')) ?>" aria-label="<?= e(t('form.times')) ?>" maxlength="300">
            <button type="button" class="btn-icon" data-remove-row title="<?= e(t('form.remove')) ?>" aria-label="<?= e(t('form.remove')) ?>">×</button>
        </div>
    <?php endforeach; ?>
</div>
<template id="<?= e($listId) ?>-tpl">
    <div class="row-item date-row">
        <input type="date" name="dates[__INDEX__][date]" aria-label="<?= e(t('form.date')) ?>">
        <input type="text" name="dates[__INDEX__][times]" placeholder="<?= e(t('form.times_placeholder')) ?>" aria-label="<?= e(t('form.times')) ?>" maxlength="300">
        <button type="button" class="btn-icon" data-remove-row title="<?= e(t('form.remove')) ?>" aria-label="<?= e(t('form.remove')) ?>">×</button>
    </div>
</template>
<button type="button" class="btn btn-ghost" data-add-row="<?= e($listId) ?>" data-kind="date">+ <?= e(t('form.add_date')) ?></button>
<p class="hint"><?= e(t('form.times_hint')) ?></p>
