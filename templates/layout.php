<?php

declare(strict_types=1);

/**
 * Common layout of every HTML page (US-22, US-24).
 *
 * @var App\View\View $this
 * @var string $content page body, already rendered and escaped
 * @var string $title
 * @var string $description
 * @var string|null $canonicalPath
 * @var bool $noindex
 * @var string|null $currentNav
 */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Products Avenue</title>
<?php if ($description !== '') { ?>
    <meta name="description" content="<?= e($description) ?>">
<?php } ?>
<?php if ($noindex) { ?>
    <meta name="robots" content="noindex">
<?php } ?>
<?php if ($canonicalPath !== null) { ?>
    <link rel="canonical" href="<?= e($this->absoluteUrl($canonicalPath)) ?>">
<?php } ?>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="/">
            <span class="brand__mark" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3L5 21M16 3l3 18M12 4v3M12 11v3M12 18v2"/></svg>
            </span>
            <span><strong>Products</strong> Avenue</span>
        </a>
        <nav class="site-nav" aria-label="Navigation principale">
            <a class="site-nav__link" href="/"<?= $currentNav === 'catalogue' ? ' aria-current="page"' : '' ?>>Catalogue</a>
        </nav>
    </div>
</header>
<main id="contenu" class="site-main">
<?= $content ?>
</main>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__about">
            <strong class="site-footer__name">Products Avenue</strong>
            <span class="muted">Catalogue fourni par FakeStore API. Avis publiés par nos clients.</span>
        </div>
        <span class="muted">© <?= e(date('Y')) ?> Products Avenue</span>
    </div>
</footer>
</body>
</html>
