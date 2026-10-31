<?php

declare(strict_types=1);

/**
 * Product page (US-23): breadcrumb, large image, title, price, description.
 * The reviews and the review form are added below the product (US-31).
 *
 * @var App\View\View $this
 * @var App\Product\Product $product
 */
?>
<div class="page product-page">
    <?= $this->render('partials/breadcrumb', ['items' => [['label' => 'Catalogue', 'url' => '/'], ['label' => $product->title, 'url' => null]]]) ?>

    <article class="product">
        <div class="product__image">
<?php if ($product->imageUrl !== null) { ?>
            <?php /* The main image is above the fold: loaded at once (no lazy loading), it is the largest element of the page. */ ?>
            <img src="<?= e($product->imageUrl) ?>" alt="<?= e($product->title) ?>" width="600" height="600" fetchpriority="high">
<?php } else { ?>
            <?= $this->render('partials/image-placeholder') ?>
<?php } ?>
        </div>
        <div class="product__summary">
            <h1 class="product__title"><?= e($product->title) ?></h1>
            <p class="product__price"><?= e($product->price->format()) ?></p>
            <p class="product__description"><?= e($product->description) ?></p>
        </div>
    </article>
</div>
