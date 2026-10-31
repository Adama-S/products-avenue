<?php

declare(strict_types=1);

/**
 * 404, 503 and 500 pages (US-22, US-24): a neutral message and a way back, never a technical detail.
 *
 * @var int $statusCode
 * @var string $heading
 * @var string $message
 */
?>
<div class="page">
    <section class="error-panel" aria-labelledby="error-title">
        <span class="error-panel__code" aria-hidden="true"><?= e($statusCode) ?></span>
        <h1 id="error-title" class="error-panel__title"><?= e($heading) ?></h1>
        <p class="error-panel__message"><?= e($message) ?></p>
        <a class="button button--primary" href="/">Retour au catalogue</a>
    </section>
</div>
