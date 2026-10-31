<?php

declare(strict_types=1);

/**
 * Edit a review (US-31): the same fields and rules as adding one, pre-filled; noindex.
 *
 * @var App\View\View $this
 * @var App\Product\Product $product
 * @var App\Comment\Comment $comment
 * @var App\Comment\CommentForm $form
 * @var int $commentsPage review page to go back to
 * @var string $backUrl
 * @var string $csrfToken
 */
?>
<div class="page product-page">
    <?= $this->render('partials/breadcrumb', ['items' => [
        ['label' => 'Catalogue', 'url' => '/'],
        ['label' => $product->title, 'url' => '/products/' . $product->id],
        ['label' => 'Modifier un avis', 'url' => null],
    ]]) ?>

    <div class="edit-review">
        <div class="edit-review__heading">
            <h1 class="edit-review__title">Modifier votre avis</h1>
            <p class="muted">Publié le <?= e(french_datetime($comment->createdAt)) ?></p>
        </div>
        <form class="review-form" method="post" action="/products/<?= e($product->id) ?>/comments/<?= e($comment->id) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="comments_page" value="<?= e($commentsPage) ?>">
            <?= $this->render('partials/comment-fields', ['form' => $form]) ?>
            <div class="review-form__actions">
                <button class="button button--primary" type="submit">Enregistrer</button>
                <a class="button button--ghost" href="<?= e($backUrl) ?>">Annuler</a>
            </div>
        </form>
    </div>
</div>
