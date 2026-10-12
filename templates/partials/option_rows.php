<?php
/**
 * Lignes de choix libres (sondage classique).
 * @var list<string> $rows
 * @var string $listId
 */
?>
<div class="row-list" id="<?= e($listId) ?>">
    <?php foreach ($rows as $i => $label): ?>
        <div class="row-item option-row">
            <input type="text" name="options[]" value="<?= e(is_string($label) ? $label : '') ?>" maxlength="255"
                   placeholder="<?= e(t('form.option_placeholder', ['n' => $i + 1])) ?>" aria-label="<?= e(t('form.option')) ?>">
            <button type="button" class="btn-icon" data-remove-row title="<?= e(t('form.remove')) ?>" aria-label="<?= e(t('form.remove')) ?>">×</button>
        </div>
    <?php endforeach; ?>
</div>
<template id="<?= e($listId) ?>-tpl">
    <div class="row-item option-row">
        <input type="text" name="options[]" maxlength="255" aria-label="<?= e(t('form.option')) ?>">
        <button type="button" class="btn-icon" data-remove-row title="<?= e(t('form.remove')) ?>" aria-label="<?= e(t('form.remove')) ?>">×</button>
    </div>
</template>
<button type="button" class="btn btn-ghost" data-add-row="<?= e($listId) ?>" data-kind="option">+ <?= e(t('form.add_option')) ?></button>
