<?php
/** Tableau des participants × choix (voir poll.php pour les variables). */
$cells = [
    App\Service\PollService::YES => ['c-yes', '✓', t('vote.yes')],
    App\Service\PollService::MAYBE => ['c-maybe', '(✓)', t('vote.maybe')],
    App\Service\PollService::NO => ['c-no', '✗', t('vote.no')],
];
$hasActions = $editable !== [];
?>
<div class="table-wrap">
    <table class="results">
        <thead>
        <tr>
            <th scope="col" class="name-col"><?= e(t('results.participant')) ?></th>
            <?php foreach ($options as $o): $best = $results[(int) $o['id']]['best']; ?>
                <th scope="col" class="opt-col<?= $best ? ' is-best' : '' ?>">
                    <?php if ($isDate): $parts = $i18n->dayParts($o['option_date']); ?>
                        <span class="d-wd"><?= e($parts['wd']) ?></span>
                        <span class="d-day"><?= e($parts['d']) ?></span>
                        <span class="d-mon"><?= e($parts['mon']) ?><?= $parts['y'] !== '' ? ' ' . e($parts['y']) : '' ?></span>
                        <?php if ($o['label'] !== ''): ?><span class="d-time"><?= e($o['label']) ?></span><?php endif; ?>
                    <?php else: ?>
                        <span class="opt-label"><?= e($o['label']) ?></span>
                    <?php endif; ?>
                </th>
            <?php endforeach; ?>
            <?php if ($hasActions): ?><th scope="col" class="actions-col"><span class="sr-only"><?= e(t('results.actions')) ?></span></th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php if (!$votes): ?>
            <tr><td colspan="<?= count($options) + 1 + ($hasActions ? 1 : 0) ?>" class="empty"><?= e(t('results.empty')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($votes as $vote): $vid = (int) $vote['id']; ?>
            <tr class="<?= $editVote && (int) $editVote['id'] === $vid ? 'is-editing' : '' ?>">
                <th scope="row" class="name-col"><?= e($vote['name']) ?></th>
                <?php foreach ($options as $o): [$class, $symbol, $label] = $cells[$vote['choices'][(int) $o['id']] ?? App\Service\PollService::NO]; ?>
                    <td class="<?= $class ?>" title="<?= e($label) ?>"><span aria-hidden="true"><?= $symbol ?></span><span class="sr-only"><?= e($label) ?></span></td>
                <?php endforeach; ?>
                <?php if ($hasActions): ?>
                    <td class="actions-col">
                        <?php if (isset($editable[$vid])): ?>
                            <a class="btn-icon" href="<?= e(url($base) . '?edit=' . $vid . '#vote') ?>" title="<?= e(t('results.edit')) ?>" aria-label="<?= e(t('results.edit_of', ['name' => $vote['name']])) ?>">✎</a>
                            <form method="post" action="<?= e(url($base . '/vote/' . $vid . '/delete')) ?>" class="inline" data-confirm="<?= e(t('results.delete_confirm', ['name' => $vote['name']])) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-icon btn-danger-icon" title="<?= e(t('results.delete')) ?>" aria-label="<?= e(t('results.delete_of', ['name' => $vote['name']])) ?>">🗑</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($votes): ?>
            <tfoot>
            <tr>
                <th scope="row" class="name-col"><?= e(t('results.total')) ?></th>
                <?php foreach ($options as $o): $res = $results[(int) $o['id']]; ?>
                    <td class="total<?= $res['best'] ? ' is-best' : '' ?>">
                        <strong><?= $res['yes'] ?></strong>
                        <?php if ($res['maybe']): ?><small>(+<?= $res['maybe'] ?>)</small><?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <?php if ($hasActions): ?><td class="actions-col"></td><?php endif; ?>
            </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>
<p class="legend">
    <span class="c-yes-text">✓ <?= e(t('vote.yes')) ?></span>
    <?php if ($poll['allow_maybe']): ?><span class="c-maybe-text">(✓) <?= e(t('vote.maybe')) ?></span><?php endif; ?>
    <span class="c-no-text">✗ <?= e(t('vote.no')) ?></span>
</p>
