<?php
/** @var string $content */
$app = App\App::get();
$lang = $app->i18n->lang();
$messages = $app->session->takeMessages();
$appName = (string) $app->config['app']['name'];
$isAdmin = false;
try {
    $isAdmin = $app->currentAdmin() !== null;
} catch (\Throwable) {
}
$returnTo = $app->request->uri();
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= isset($title) && $title !== '' ? e($title) . ' · ' : '' ?><?= e($appName) ?></title>
    <link rel="icon" href="<?= e(url('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('/assets/style.css')) ?>?v=1">
    <script src="<?= e(url('/assets/app.js')) ?>?v=1" defer></script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-mark" aria-hidden="true">BC</span>
            <span class="brand-text">BC Swing <small><?= e(t('nav.polls')) ?></small></span>
        </a>
        <nav class="header-nav">
            <?php if ($isAdmin): ?>
                <a href="<?= e(url('/admin')) ?>"><?= e(t('nav.admin')) ?></a>
            <?php endif; ?>
            <span class="lang-switch">
                <?php foreach (App\I18n::SUPPORTED as $code): ?>
                    <?php if ($code === $lang): ?>
                        <strong aria-current="true"><?= strtoupper($code) ?></strong>
                    <?php else: ?>
                        <a href="<?= e(url('/lang/' . $code) . '?r=' . rawurlencode($returnTo)) ?>" hreflang="<?= $code ?>" lang="<?= $code ?>"><?= strtoupper($code) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </span>
        </nav>
    </div>
</header>

<main class="container main">
    <?php foreach ($messages as $m): ?>
        <div class="alert alert-<?= e($m['type']) ?>" role="status"><?= e(t($m['key'], $m['params'])) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container">
        <span><?= e($appName) ?></span>
        <span class="sep">·</span>
        <a href="<?= e(url('/')) ?>"><?= e(t('nav.new_poll')) ?></a>
    </div>
</footer>
</body>
</html>
