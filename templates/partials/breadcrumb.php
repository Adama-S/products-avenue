<?php

declare(strict_types=1);

/**
 * Breadcrumb (US-23): « Catalogue / Product », or three levels on the review edit page (US-31).
 *
 * An ordered list in a labelled nav; the separators are hidden from screen readers; the
 * last item is the current page: aria-current, and not a link.
 *
 * @var list<array{label: string, url: string|null}> $items the last one is the current page
 */
$lastIndex = array_key_last($items);
?>
<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <ol class="breadcrumb__list">
<?php foreach ($items as $index => $item) { ?>
<?php if ($index === $lastIndex) { ?>
        <li aria-current="page"><?= e($item['label']) ?></li>
<?php } else { ?>
        <li><a href="<?= e((string) $item['url']) ?>"><?= e($item['label']) ?></a></li>
        <li class="breadcrumb__separator" aria-hidden="true">/</li>
<?php } ?>
<?php } ?>
    </ol>
</nav>
