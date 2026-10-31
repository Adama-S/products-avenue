<?php

declare(strict_types=1);

/**
 * Fields of the review form, shared by adding and editing (US-31).
 *
 * Each error is shown next to its field and linked to it (aria-describedby), with a summary
 * on top (role="alert"). The content field has no maxlength: the server refuses a too-long
 * review and says why; app.js adds a character counter.
 *
 * @var App\Comment\CommentForm $form
 */
$errorCount = count($form->errors);
$authorError = $form->errors['author'] ?? null;
$contentError = $form->errors['content'] ?? null;
?>
<?php if ($errorCount > 0) { ?>
<div class="form-alert" role="alert">Le formulaire contient <?= e($errorCount) ?> <?= $errorCount === 1 ? 'erreur' : 'erreurs' ?>.</div>
<?php } ?>
<div class="field">
    <label for="author">Votre nom</label>
    <input class="input<?= $authorError !== null ? ' input--error' : '' ?>" id="author" name="author" type="text" maxlength="100" autocomplete="name" value="<?= e($form->author) ?>"<?= $authorError !== null ? ' aria-invalid="true" aria-describedby="author-error"' : '' ?>>
<?php if ($authorError !== null) { ?>
    <span id="author-error" class="field__error"><?= e($authorError) ?></span>
<?php } ?>
</div>
<div class="field">
    <label for="content">Votre avis</label>
    <textarea class="input<?= $contentError !== null ? ' input--error' : '' ?>" id="content" name="content" rows="5" data-max-length="1000"<?= $contentError !== null ? ' aria-invalid="true" aria-describedby="content-error"' : '' ?>><?= e($form->content) ?></textarea>
    <?php /* The error on the left; app.js adds the character counter on the right. */ ?>
    <div class="field__footer">
<?php if ($contentError !== null) { ?>
        <span id="content-error" class="field__error"><?= e($contentError) ?></span>
<?php } ?>
    </div>
</div>
