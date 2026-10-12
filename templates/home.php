<section class="hero">
    <h1><?= e(t('home.heading')) ?></h1>
    <p class="lead"><?= e(t('home.lead')) ?></p>
</section>

<div class="choice-grid">
    <a class="choice-card" href="<?= e(url('/create/date')) ?>">
        <span class="choice-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M7 2h2v2h6V2h2v2h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3V2zm12 8H5v9h14v-9zM5 8h14V6H5v2zm2 4h3v3H7v-3z"/></svg>
        </span>
        <span class="choice-title"><?= e(t('home.date.title')) ?></span>
        <span class="choice-desc"><?= e(t('home.date.desc')) ?></span>
        <span class="btn btn-primary"><?= e(t('home.create')) ?></span>
    </a>
    <a class="choice-card" href="<?= e(url('/create/classic')) ?>">
        <span class="choice-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M4 5h2v2H4V5zm4 0h12v2H8V5zm-4 6h2v2H4v-2zm4 0h12v2H8v-2zm-4 6h2v2H4v-2zm4 0h12v2H8v-2z"/></svg>
        </span>
        <span class="choice-title"><?= e(t('home.classic.title')) ?></span>
        <span class="choice-desc"><?= e(t('home.classic.desc')) ?></span>
        <span class="btn btn-primary"><?= e(t('home.create')) ?></span>
    </a>
</div>

<section class="how card">
    <h2><?= e(t('home.how.title')) ?></h2>
    <ol class="steps">
        <li><?= e(t('home.how.step1')) ?></li>
        <li><?= e(t('home.how.step2')) ?></li>
        <li><?= e(t('home.how.step3')) ?></li>
    </ol>
</section>
