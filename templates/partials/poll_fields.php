<?php
/**
 * Champs communs à la création et aux paramètres d'un sondage.
 * @var array $values  valeurs du formulaire
 * @var array $errors
 * @var string $idPrefix
 */
$v = static fn (string $k, $d = '') => is_string($values[$k] ?? null) ? $values[$k] : $d;
$policy = $v('edit_policy', 'own');
?>
<fieldset class="fieldset">
    <legend><?= e(t('form.section.info')) ?></legend>
    <div class="grid-2">
        <div class="field">
            <label for="<?= $idPrefix ?>author_name"><?= e(t('form.author_name')) ?> <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="<?= $idPrefix ?>author_name" name="author_name" required maxlength="100" autocomplete="name"
                   value="<?= e($v('author_name')) ?>">
            <?= field_error($errors, 'author_name') ?>
        </div>
        <div class="field">
            <label for="<?= $idPrefix ?>author_email"><?= e(t('form.author_email')) ?> <span class="optional"><?= e(t('form.optional')) ?></span></label>
            <input type="email" id="<?= $idPrefix ?>author_email" name="author_email" maxlength="190" autocomplete="email"
                   value="<?= e($v('author_email')) ?>">
            <p class="hint"><?= e(t('form.author_email_hint')) ?></p>
            <?= field_error($errors, 'author_email') ?>
        </div>
    </div>
    <div class="field">
        <label for="<?= $idPrefix ?>title"><?= e(t('form.title')) ?> <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="<?= $idPrefix ?>title" name="title" required maxlength="200" value="<?= e($v('title')) ?>">
        <?= field_error($errors, 'title') ?>
    </div>
    <div class="field">
        <label for="<?= $idPrefix ?>description"><?= e(t('form.description')) ?> <span class="optional"><?= e(t('form.optional')) ?></span></label>
        <textarea id="<?= $idPrefix ?>description" name="description" rows="4" maxlength="5000"><?= e($v('description')) ?></textarea>
        <?= field_error($errors, 'description') ?>
    </div>
</fieldset>

<fieldset class="fieldset">
    <legend><?= e(t('form.section.settings')) ?></legend>

    <div class="field">
        <span class="label"><?= e(t('form.edit_policy')) ?></span>
        <div class="radio-cards">
            <?php foreach (['own', 'all', 'none'] as $option): ?>
                <label class="radio-card">
                    <input type="radio" name="edit_policy" value="<?= $option ?>" <?= $policy === $option ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e(t('policy.' . $option)) ?></strong>
                        <small><?= e(t('policy.' . $option . '.desc')) ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'edit_policy') ?>
    </div>

    <div class="field">
        <label class="checkbox">
            <input type="checkbox" name="allow_maybe" value="1" <?= !empty($values['allow_maybe']) ? 'checked' : '' ?>>
            <span><?= e(t('form.allow_maybe')) ?></span>
        </label>
    </div>

    <div class="field">
        <label for="<?= $idPrefix ?>deadline"><?= e(t('form.deadline')) ?> <span class="optional"><?= e(t('form.optional')) ?></span></label>
        <input type="datetime-local" id="<?= $idPrefix ?>deadline" name="deadline" value="<?= e($v('deadline')) ?>" class="input-auto">
        <p class="hint"><?= e(t('form.deadline_hint')) ?></p>
        <?= field_error($errors, 'deadline') ?>
    </div>
</fieldset>
