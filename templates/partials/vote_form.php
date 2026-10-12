<?php
/** Formulaire de vote : nouveau participant ou modification (voir poll.php pour les variables). */
use App\Service\PollService;

$editing = $editVote !== null;
$name = $editing ? $editVote['name'] : '';
$choices = $editing ? $editVote['choices'] : [];
// Après une erreur de validation, on réaffiche la saisie.
if (is_array($voteOld) && (int) ($voteOld['vote_id'] ?? 0) === ($editing ? (int) $editVote['id'] : 0)) {
    $name = is_string($voteOld['name'] ?? null) ? $voteOld['name'] : $name;
    $choices = is_array($voteOld['choices'] ?? null) ? array_map('intval', array_filter($voteOld['choices'], 'is_numeric')) : $choices;
}
$values = [PollService::YES => ['yes', '✓', t('vote.yes')]];
if ($poll['allow_maybe']) {
    $values[PollService::MAYBE] = ['maybe', '(✓)', t('vote.maybe')];
}
$values[PollService::NO] = ['no', '✗', t('vote.no')];
?>
<h2><?= e(t($editing ? 'vote.edit_title' : 'vote.title')) ?></h2>
<?php if ($voteErrors): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($voteErrors as $field => $key): ?><p><?= e(t($key)) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($manager && !$isOpen): ?>
    <p class="hint"><?= e(t('vote.manager_closed_hint')) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(url($base . '/vote')) ?>" class="vote-form">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="vote_id" value="<?= (int) $editVote['id'] ?>">
    <?php endif; ?>

    <div class="field">
        <label for="vote-name"><?= e(t('vote.name')) ?> <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="vote-name" name="name" required maxlength="100" autocomplete="name" value="<?= e($name) ?>">
    </div>

    <ul class="vote-options">
        <?php foreach ($options as $o): $oid = (int) $o['id']; $current = $choices[$oid] ?? PollService::NO; ?>
            <li class="vote-option">
                <div class="vo-label" id="vo-<?= $oid ?>">
                    <?php if ($isDate): ?>
                        <strong><?= e($i18n->formatDay($o['option_date'])) ?></strong>
                        <?php if ($o['label'] !== ''): ?><span class="vo-time"><?= e($o['label']) ?></span><?php endif; ?>
                    <?php else: ?>
                        <strong><?= e($o['label']) ?></strong>
                    <?php endif; ?>
                </div>
                <div class="seg" role="radiogroup" aria-labelledby="vo-<?= $oid ?>">
                    <?php foreach ($values as $value => [$cls, $symbol, $label]): ?>
                        <label class="seg-<?= $cls ?>">
                            <input type="radio" name="choices[<?= $oid ?>]" value="<?= $value ?>" <?= $current === $value ? 'checked' : '' ?>>
                            <span><b aria-hidden="true"><?= $symbol ?></b> <?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><?= e(t($editing ? 'vote.update' : 'vote.submit')) ?></button>
        <?php if ($editing): ?>
            <a class="btn btn-ghost" href="<?= e(url($base)) ?>#results"><?= e(t('common.cancel')) ?></a>
        <?php endif; ?>
    </div>
</form>
