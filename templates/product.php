<?php

declare(strict_types=1);

/**
 * Product page (US-23, US-31): breadcrumb, product, one page of reviews, review form.
 *
 * @var App\View\View $this
 * @var App\Product\Product $product
 * @var list<App\Comment\Comment> $comments reviews of this page, newest first
 * @var App\Pagination\Pagination $pagination of the reviews
 * @var Closure(int): string $commentsPageUrl
 * @var App\Comment\CommentForm $form what the visitor typed, with its errors
 * @var App\Comment\CommentNotice|null $notice confirmation of the last form
 * @var string $csrfToken
 */
$reviewCount = $pagination->totalItems;
?>
<div class="page product-page">
    <?= $this->render('partials/breadcrumb', ['items' => [['label' => 'Catalogue', 'url' => '/'], ['label' => $product->title, 'url' => null]]]) ?>

<?php if ($notice !== null) { ?>
    <div class="notice" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
        <?= e($notice->message()) ?>
    </div>
<?php } ?>

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
<?php if ($reviewCount === 0) { ?>
            <span class="muted">Pas encore d'avis</span>
<?php } else { ?>
            <a class="product__reviews-link" href="#avis"><?= $reviewCount === 1 ? "Lire l'avis" : 'Lire les ' . e($reviewCount) . ' avis' ?> ↓</a>
<?php } ?>
        </div>
    </article>

    <section class="reviews" aria-labelledby="avis">
        <div class="reviews__list">
            <div class="reviews__header">
<?php if ($reviewCount === 0) { ?>
                <h2 id="avis" class="reviews__title">Avis clients</h2>
<?php } else { ?>
                <h2 id="avis" class="reviews__title">Avis clients <span class="reviews__count">(<?= e($reviewCount) ?>)</span></h2>
                <span class="muted">Avis <?= e($pagination->firstItemNumber()) ?> à <?= e($pagination->lastItemNumber()) ?> sur <?= e($reviewCount) ?></span>
<?php } ?>
            </div>

<?php if ($comments === []) { ?>
            <div class="reviews__empty">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.4A8 8 0 1 1 21 12z"/></svg>
                <strong>Aucun avis pour l'instant.</strong>
                <span class="muted">Soyez la première personne à donner le vôtre.</span>
            </div>
<?php } ?>

<?php foreach ($comments as $comment) { ?>
            <article class="review">
                <span class="review__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($comment->author, 0, 1))) ?></span>
                <div class="review__body">
                    <div class="review__header">
                        <strong class="review__author"><?= e($comment->author) ?></strong>
                        <span class="review__date"><?= e(french_datetime($comment->createdAt)) ?><?= $comment->isEdited() ? ' · modifié' : '' ?></span>
                    </div>
                    <p class="review__content"><?= e($comment->content) ?></p>
                    <div class="review__actions">
                        <a class="button button--ghost button--small" href="/products/<?= e($product->id) ?>/comments/<?= e($comment->id) ?>/edit<?= $pagination->currentPage > 1 ? '?comments_page=' . e($pagination->currentPage) : '' ?>">Modifier</a>
                        <?php /* POST only, with the CSRF token; app.js asks for a confirmation, the form works without it. */ ?>
                        <form class="review__delete" method="post" action="/products/<?= e($product->id) ?>/comments/<?= e($comment->id) ?>/delete" data-confirm="Supprimer cet avis ? Cette action est définitive.">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="comments_page" value="<?= e($pagination->currentPage) ?>">
                            <button class="button button--danger button--small" type="submit">Supprimer</button>
                        </form>
                    </div>
                </div>
            </article>
<?php } ?>

            <?= $this->render('partials/pagination', [
                'pagination' => $pagination,
                'pageUrl' => $commentsPageUrl,
                'label' => 'Pagination des avis',
                'previousLabel' => '← Avis précédents',
                'nextLabel' => 'Avis suivants →',
                'compact' => true,
            ]) ?>
        </div>

        <?php /* novalidate: the server checks every field and explains each error next to it. */ ?>
        <form class="review-form" method="post" action="/products/<?= e($product->id) ?>/comments" novalidate>
            <h2 class="review-form__title">Donner votre avis</h2>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <?= $this->render('partials/comment-fields', ['form' => $form]) ?>
            <button class="button button--primary" type="submit">Publier mon avis</button>
        </form>
    </section>
</div>
