<?php

declare(strict_types=1);

/**
 * Catalogue page (US-22): heading, page size form, product grid, pagination.
 *
 * @var App\View\View $this
 * @var list<App\Product\Product> $products products of this page
 * @var array<int, int> $reviewCounts review count per product id
 * @var App\Pagination\Pagination $pagination
 * @var list<int> $perPageOptions
 * @var Closure(int): string $pageUrl
 */
?>
<div class="page catalogue">
    <div class="catalogue__header">
        <div class="page-heading">
            <p class="page-heading__eyebrow">Catalogue</p>
            <h1 class="page-heading__title">Tous les produits</h1>
            <p class="muted"><?= e($pagination->totalItems) ?> produits · page <?= e($pagination->currentPage) ?> sur <?= e($pagination->pageCount) ?></p>
        </div>
        <?php /* A plain GET form: works without JavaScript, and the choice stays in the URL. */ ?>
        <form class="per-page" method="get" action="/">
            <label for="per-page" class="muted">Afficher</label>
            <select id="per-page" name="per_page" class="select">
<?php foreach ($perPageOptions as $option) { ?>
                <option value="<?= e($option) ?>"<?= $option === $pagination->perPage ? ' selected' : '' ?>><?= e($option) ?> par page</option>
<?php } ?>
            </select>
            <button class="button button--ghost" type="submit">OK</button>
        </form>
    </div>

    <ul class="product-grid">
<?php foreach ($products as $product) { ?>
        <li>
            <a class="product-card" href="/products/<?= e($product->id) ?>">
                <div class="product-card__image">
<?php if ($product->imageUrl !== null) { ?>
                    <img src="<?= e($product->imageUrl) ?>" alt="<?= e($product->title) ?>" width="300" height="300" loading="lazy">
<?php } else { ?>
                    <?= $this->render('partials/image-placeholder') ?>
<?php } ?>
                </div>
                <div class="product-card__body">
                    <h2 class="product-card__title"><?= e($product->title) ?></h2>
                    <div class="product-card__meta">
                        <span class="product-card__price"><?= e($product->price->format()) ?></span>
                        <span class="product-card__reviews">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.4A8 8 0 1 1 21 12z"/></svg>
                            <?= e($reviewCounts[$product->id] ?? 0) ?> avis
                        </span>
                    </div>
                </div>
            </a>
        </li>
<?php } ?>
    </ul>

    <?= $this->render('partials/pagination', [
        'pagination' => $pagination,
        'pageUrl' => $pageUrl,
        'label' => 'Pagination',
        'previousLabel' => '← Précédent',
        'nextLabel' => 'Suivant →',
    ]) ?>
</div>
