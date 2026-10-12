<?php
/**
 * @var int $status
 * @var string $message
 * @var ?string $detail
 */
?>
<div class="card error-page">
    <p class="error-code"><?= (int) $status ?></p>
    <h1><?= e($message) ?></h1>
    <?php if (!empty($detail)): ?>
        <pre class="debug"><?= e($detail) ?></pre>
    <?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(t('nav.back_home')) ?></a></p>
</div>
