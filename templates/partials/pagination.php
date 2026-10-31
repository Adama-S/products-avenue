<?php

declare(strict_types=1);

/**
 * Accessible pagination (US-22, reused for the reviews in US-31).
 *
 * The current page is marked with aria-current and is not a link; Previous and Next are
 * absent at the ends (no dead link); rel="prev" and rel="next" help crawlers.
 *
 * @var App\Pagination\Pagination $pagination
 * @var Closure(int): string $pageUrl
 * @var string $label accessible name of the navigation
 * @var string $previousLabel
 * @var string $nextLabel
 * @var bool|null $compact smaller, left-aligned variant (reviews)
 */

if ($pagination->pageCount === 1) {
    return;
}
?>
<nav class="pagination<?= ($compact ?? false) ? ' pagination--compact' : '' ?>" aria-label="<?= e($label) ?>">
<?php if ($pagination->hasPrevious()) { ?>
    <a class="pagination__link" href="<?= e($pageUrl($pagination->currentPage - 1)) ?>" rel="prev"><?= e($previousLabel) ?></a>
<?php } ?>
<?php foreach ($pagination->pages() as $number) { ?>
<?php if ($number === $pagination->currentPage) { ?>
    <span class="pagination__current" aria-current="page"><?= e($number) ?></span>
<?php } else { ?>
    <a class="pagination__link" href="<?= e($pageUrl($number)) ?>"><?= e($number) ?></a>
<?php } ?>
<?php } ?>
<?php if ($pagination->hasNext()) { ?>
    <a class="pagination__link" href="<?= e($pageUrl($pagination->currentPage + 1)) ?>" rel="next"><?= e($nextLabel) ?></a>
<?php } ?>
</nav>
