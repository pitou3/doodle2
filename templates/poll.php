<?php
/**
 * Page d'un sondage (publique ou gestion).
 * @var array $poll
 * @var list<array> $options
 * @var list<array> $votes
 * @var array $results
 * @var string $base
 * @var bool $manager
 * @var array<int, bool> $editable
 * @var ?array $editVote
 * @var bool $isOpen
 * @var bool $canAdd
 * @var DateTimeImmutable $purgeDate
 * @var string $publicUrl
 * @var array $voteErrors
 * @var ?array $voteOld
 * @var ?string $voteLink
 */
$i18n = App\App::get()->i18n;
$isDate = $poll['type'] === 'date';
?>
<?php if ($manager): ?>
    <?= $this->partial('partials/manage_panel', get_defined_vars()) ?>
<?php endif; ?>

<article class="card poll-head">
    <div class="badges">
        <span class="badge badge-type"><?= e(t($isDate ? 'poll.type.date' : 'poll.type.classic')) ?></span>
        <?php if ($isOpen): ?>
            <span class="badge badge-open"><?= e(t('poll.status.open')) ?></span>
        <?php else: ?>
            <span class="badge badge-closed"><?= e(t('poll.status.archived')) ?></span>
        <?php endif; ?>
    </div>
    <h1><?= e($poll['title']) ?></h1>
    <p class="meta"><?= e(t('poll.created_by', ['name' => $poll['author_name'], 'date' => $i18n->formatDate($poll['created_at'])])) ?></p>
    <?php if ($poll['description'] !== null && $poll['description'] !== ''): ?>
        <div class="description"><?= nl2br(e($poll['description'])) ?></div>
    <?php endif; ?>

    <ul class="poll-facts">
        <?php if ($isOpen && $poll['ends_at'] !== null): ?>
            <li><?= e(t('poll.open_until', ['date' => $i18n->formatDateTime($poll['ends_at'])])) ?></li>
        <?php elseif (!$isOpen): ?>
            <li class="text-warning"><?= e(t('poll.closed_since', ['date' => $i18n->formatDateTime($poll['ends_at']), 'purge' => $i18n->formatDate($purgeDate->format('Y-m-d'))])) ?></li>
        <?php endif; ?>
        <li><?= e(t('poll.policy_info.' . $poll['edit_policy'])) ?></li>
    </ul>

    <?php if ($isOpen && !$editVote): ?>
        <a href="#vote" class="btn btn-primary"><?= e(t('poll.participate')) ?></a>
    <?php endif; ?>
</article>

<?php if ($voteLink): ?>
    <div class="alert alert-info">
        <p><strong><?= e(t('vote.personal_link.title')) ?></strong> <?= e(t('vote.personal_link.desc')) ?></p>
        <div class="copy-field">
            <input type="text" readonly value="<?= e($voteLink) ?>" id="vote-link" aria-label="<?= e(t('vote.personal_link.title')) ?>">
            <button type="button" class="btn btn-secondary" data-copy="vote-link" data-copied="<?= e(t('common.copied')) ?>"><?= e(t('common.copy')) ?></button>
        </div>
    </div>
<?php endif; ?>

<section class="card" id="results">
    <h2><?= e(t('results.title')) ?> <span class="count">(<?= count($votes) ?>)</span></h2>
    <?= $this->partial('partials/results_table', get_defined_vars()) ?>
</section>

<section class="card" id="vote">
    <?php if ($editVote || $canAdd): ?>
        <?= $this->partial('partials/vote_form', get_defined_vars()) ?>
    <?php else: ?>
        <h2><?= e(t('vote.title')) ?></h2>
        <p class="muted"><?= e(t('vote.closed')) ?></p>
    <?php endif; ?>
</section>

<?php if ($votes): ?>
    <section class="card" id="summary">
        <h2><?= e(t('summary.title')) ?></h2>
        <ul class="summary-list">
            <?php foreach ($options as $o): $res = $results[(int) $o['id']]; ?>
                <li class="<?= $res['best'] ? 'is-best' : '' ?>">
                    <div class="summary-head">
                        <span class="summary-label">
                            <?php if ($isDate): ?>
                                <?= e($i18n->formatDay($o['option_date'])) ?><?= $o['label'] !== '' ? ' · ' . e($o['label']) : '' ?>
                            <?php else: ?>
                                <?= e($o['label']) ?>
                            <?php endif; ?>
                            <?php if ($res['best']): ?><span class="badge badge-best">★ <?= e(t('results.best')) ?></span><?php endif; ?>
                        </span>
                        <span class="summary-count">
                            <span class="c-yes-text">✓ <?= $res['yes'] ?></span>
                            <?php if ($poll['allow_maybe'] || $res['maybe']): ?>
                                <span class="c-maybe-text">(✓) <?= $res['maybe'] ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($res['yes_names'] || $res['maybe_names']): ?>
                        <p class="summary-names">
                            <?= e(implode(', ', $res['yes_names'])) ?>
                            <?php if ($res['maybe_names']): ?>
                                <span class="muted"><?= $res['yes_names'] ? ' · ' : '' ?>(<?= e(implode(', ', $res['maybe_names'])) ?>)</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
