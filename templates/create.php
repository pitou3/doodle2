<?php
/**
 * @var string $type date|classic
 * @var array $errors
 * @var ?array $old
 */
$isDate = $type === 'date';
$values = $old ?? ['allow_maybe' => '1', 'edit_policy' => 'own'];
if ($isDate) {
    $rows = is_array($old['dates'] ?? null) ? array_values(array_filter($old['dates'], 'is_array')) : [];
    $rows = $rows ?: [[], [], []];
} else {
    $rows = is_array($old['options'] ?? null) ? array_values($old['options']) : [];
    $rows = $rows ?: ['', '', ''];
}
?>
<div class="page-head">
    <a class="back" href="<?= e(url('/')) ?>">← <?= e(t('nav.back_home')) ?></a>
    <h1><?= e(t($isDate ? 'create.title_date' : 'create.title_classic')) ?></h1>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><?= e(t('form.has_errors')) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/create/' . $type)) ?>" class="card form" novalidate>
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <?= $this->partial('partials/poll_fields', ['values' => $values, 'errors' => $errors, 'idPrefix' => 'c-']) ?>

    <fieldset class="fieldset">
        <legend><?= e(t($isDate ? 'form.section.dates' : 'form.section.options')) ?> <span class="req" aria-hidden="true">*</span></legend>
        <?= field_error($errors, 'options') ?>
        <?php if ($isDate): ?>
            <?= $this->partial('partials/date_rows', ['rows' => $rows, 'listId' => 'new-dates']) ?>
        <?php else: ?>
            <?= $this->partial('partials/option_rows', ['rows' => $rows, 'listId' => 'new-options']) ?>
        <?php endif; ?>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><?= e(t('create.submit')) ?></button>
    </div>
</form>
