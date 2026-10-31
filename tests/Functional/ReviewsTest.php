<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FunctionalTestCase;

/**
 * Reviews on the product page: list, pagination, add, edit, delete, and their protections.
 */
final class ReviewsTest extends FunctionalTestCase
{
    public function test_a_product_without_reviews_invites_the_first_one(): void
    {
        $body = $this->get('/products/1')->body;

        self::assertStringContainsString("Aucun avis pour l'instant.", $body);
        self::assertStringContainsString('Soyez la première personne à donner le vôtre.', $body);
        self::assertStringContainsString("Pas encore d'avis", $body);
        self::assertStringContainsString('<h2 id="avis" class="reviews__title">Avis clients</h2>', $body);
    }

    public function test_reviews_are_listed_newest_first_with_french_dates_and_the_edited_mark(): void
    {
        $this->insertReview(1, 'Léa', '2026-10-28 08:10:00', updatedAt: '2026-10-29 09:00:00');
        $this->insertReview(1, 'Camille', '2026-10-30 23:26:00');
        $this->insertReview(2, 'Other product', '2026-10-31 10:00:00');

        $body = $this->get('/products/1')->body;

        self::assertMatchesRegularExpression('#Camille.*Léa#s', $body);
        self::assertStringNotContainsString('Other product', $body);
        self::assertStringContainsString('31 octobre 2026 à 00:26', $body);
        // Only the edited review is marked as such.
        self::assertSame(1, substr_count($body, ' · modifié'));
        self::assertStringContainsString('28 octobre 2026 à 09:10 · modifié', $body);
        self::assertStringContainsString('Avis clients <span class="reviews__count">(2)</span>', $body);
        self::assertStringContainsString('<a class="product__reviews-link" href="#avis">Lire les 2 avis ↓</a>', $body);
    }

    public function test_reviews_are_paginated_ten_per_page(): void
    {
        $this->insertReviews(1, 12);

        $first = $this->get('/products/1')->body;
        self::assertSame(10, substr_count($first, 'class="review"'));
        self::assertStringContainsString('Avis 1 à 10 sur 12', $first);
        self::assertStringContainsString('aria-label="Pagination des avis"', $first);
        self::assertStringContainsString('href="/products/1?comments_page=2#avis" rel="next"', $first);

        $second = $this->get('/products/1?comments_page=2')->body;
        self::assertSame(2, substr_count($second, 'class="review"'));
        self::assertStringContainsString('Avis 11 à 12 sur 12', $second);
        self::assertStringContainsString('Author 1<', $second);
        self::assertStringContainsString('href="/products/1#avis" rel="prev"', $second);
        // One indexed URL per product: the review page is not part of it.
        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/products/1">', $second);
    }

    public function test_a_review_page_after_the_last_one_returns_a_not_found_page(): void
    {
        // Every invalid form of ?comments_page is unit-tested in PaginationTest.
        $this->insertReview(1, 'Camille');

        $response = $this->get('/products/1?comments_page=2');

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Page introuvable', $response->body);
    }

    public function test_a_valid_review_is_published_with_post_redirect_get(): void
    {
        $response = $this->postForm('/products/1/comments', ['author' => '  Camille ', 'content' => "Très pratique.\r\nBien rembourré."]);

        self::assertSame(303, $response->statusCode);
        self::assertSame('/products/1?review=published#avis', $response->headers['Location']);
        self::assertSame([['Camille', "Très pratique.\nBien rembourré."]], $this->storedReviews());

        $page = $this->get('/products/1?review=published')->body;
        self::assertStringContainsString('role="status"', $page);
        self::assertStringContainsString('Votre avis est publié.', $page);
        self::assertStringContainsString('Très pratique.', $page);
    }

    public function test_an_empty_review_is_refused_with_an_error_next_to_each_field(): void
    {
        $response = $this->postForm('/products/1/comments', ['author' => ' ', 'content' => '']);

        self::assertSame(422, $response->statusCode);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertStringContainsString('Le formulaire contient 2 erreurs.', $response->body);
        self::assertStringContainsString('aria-invalid="true" aria-describedby="author-error"', $response->body);
        self::assertStringContainsString('<span id="author-error" class="field__error">Indiquez votre nom.</span>', $response->body);
        self::assertStringContainsString('<span id="content-error" class="field__error">Écrivez votre avis.</span>', $response->body);
        self::assertSame([], $this->storedReviews());
    }

    public function test_a_too_long_review_is_refused_and_the_input_is_kept(): void
    {
        $content = str_repeat('x', 1001);

        $response = $this->postForm('/products/1/comments', ['author' => 'Camille', 'content' => $content]);

        self::assertSame(422, $response->statusCode);
        self::assertStringContainsString('Le formulaire contient 1 erreur.', $response->body);
        self::assertStringContainsString("1\u{202f}000 caractères maximum.", $response->body);
        self::assertStringContainsString('value="Camille"', $response->body);
        self::assertStringContainsString('>' . $content . '</textarea>', $response->body);
        // No maxlength on the field: the browser lets the text through, so the server can explain the limit.
        self::assertDoesNotMatchRegularExpression('#<textarea[^>]*maxlength#', $response->body);
        self::assertSame([], $this->storedReviews());
    }

    public function test_a_review_that_is_not_utf8_is_refused_without_a_server_error(): void
    {
        $response = $this->postForm('/products/1/comments', ['author' => 'Camille', 'content' => "Tr\xE8s bien."]);

        self::assertSame(422, $response->statusCode);
        self::assertStringContainsString('Ce texte contient des caractères non valides.', $response->body);
        // Shown back through e(): the invalid bytes are replaced, the page stays valid UTF-8.
        self::assertTrue(mb_check_encoding($response->body, 'UTF-8'));
        self::assertSame([], $this->storedReviews());
    }

    public function test_a_review_on_an_unknown_product_is_refused(): void
    {
        $response = $this->postForm('/products/9999/comments', ['author' => 'Camille', 'content' => 'Bien.']);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Produit introuvable', $response->body);
        self::assertSame([], $this->storedReviews());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function forgedTokens(): iterable
    {
        yield 'wrong token' => [['csrf_token' => str_repeat('0', 64)]];
        yield 'missing token' => [[]];
    }

    /**
     * @param array<string, string> $token
     */
    #[DataProvider('forgedTokens')]
    public function test_a_form_without_the_session_token_is_refused(array $token): void
    {
        $this->get('/products/1');

        $response = $this->post('/products/1/comments', $token + ['author' => 'Camille', 'content' => 'Bien.']);

        self::assertSame(403, $response->statusCode);
        self::assertStringContainsString('Formulaire expiré', $response->body);
        self::assertSame([], $this->storedReviews());
    }

    public function test_every_form_carries_the_csrf_token_and_deletion_asks_for_a_confirmation(): void
    {
        $id = $this->insertReview(1, 'Camille');

        $body = $this->get('/products/1')->body;

        // The review form and the delete form of the review.
        self::assertSame(2, preg_match_all('#<input type="hidden" name="csrf_token" value="[0-9a-f]{64}">#', $body));
        // Deletion is a POST form; app.js asks for a confirmation, it works without it.
        self::assertMatchesRegularExpression('#<form [^>]*method="post" action="/products/1/comments/' . $id . '/delete" data-confirm="[^"]+"#', $body);
    }

    public function test_the_edit_form_is_prefilled_with_a_three_level_breadcrumb_and_not_indexed(): void
    {
        $id = $this->insertReview(1, 'Inès', '2026-10-30 17:45:00');

        $response = $this->get('/products/1/comments/' . $id . '/edit?comments_page=1');

        self::assertSame(200, $response->statusCode);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $response->body);
        self::assertStringContainsString('<h1 class="edit-review__title">Modifier votre avis</h1>', $response->body);
        self::assertStringContainsString('Publié le 30 octobre 2026 à 18:45', $response->body);
        self::assertStringContainsString('value="Inès"', $response->body);
        self::assertStringContainsString('>Content of Inès</textarea>', $response->body);
        self::assertMatchesRegularExpression('#<input type="hidden" name="csrf_token" value="[0-9a-f]{64}">#', $response->body);
        self::assertMatchesRegularExpression('#<a href="/">Catalogue</a>.*<a href="/products/1">Product 1</a>.*<li aria-current="page">Modifier un avis</li>#s', $response->body);
    }

    public function test_an_update_returns_to_the_review_page_of_origin(): void
    {
        // The oldest is alone on page 2.
        $oldest = $this->insertReviews(1, 12)[0];

        $response = $this->postForm('/products/1/comments/' . $oldest, ['author' => 'Author 1', 'content' => 'Changed my mind.', 'comments_page' => '2']);

        self::assertSame(303, $response->statusCode);
        self::assertSame('/products/1?comments_page=2&review=updated#avis', $response->headers['Location']);
        $page = $this->get('/products/1?comments_page=2&review=updated')->body;
        self::assertStringContainsString('Votre avis est modifié.', $page);
        self::assertStringContainsString('Changed my mind.', $page);
        self::assertStringContainsString(' · modifié', $page);
    }

    public function test_an_invalid_update_shows_the_edit_form_again(): void
    {
        $id = $this->insertReview(1, 'Camille');

        $response = $this->postForm('/products/1/comments/' . $id, ['author' => 'Camille B.', 'content' => '   ']);

        self::assertSame(422, $response->statusCode);
        self::assertStringContainsString('Modifier votre avis', $response->body);
        self::assertStringContainsString('value="Camille B."', $response->body);
        self::assertStringContainsString('Écrivez votre avis.', $response->body);
        self::assertSame([['Camille', 'Content of Camille']], $this->storedReviews());
    }

    public function test_a_deletion_returns_to_the_last_page_that_still_exists(): void
    {
        $onlyReviewOfPageTwo = $this->insertReviews(1, 11)[0];

        $response = $this->postForm('/products/1/comments/' . $onlyReviewOfPageTwo . '/delete', ['comments_page' => '2']);

        self::assertSame(303, $response->statusCode);
        self::assertSame('/products/1?review=deleted#avis', $response->headers['Location']);
        self::assertCount(10, $this->storedReviews());
        self::assertStringContainsString('Votre avis est supprimé.', $this->get('/products/1?review=deleted')->body);
    }

    public function test_a_review_is_deleted_by_post_only(): void
    {
        $id = $this->insertReview(1, 'Camille');

        self::assertSame(404, $this->get('/products/1/comments/' . $id . '/delete')->statusCode);
        self::assertCount(1, $this->storedReviews());
    }

    public function test_a_review_of_another_product_is_not_found(): void
    {
        $id = $this->insertReview(1, 'Camille');

        $edit = $this->get('/products/2/comments/' . $id . '/edit');
        $update = $this->postForm('/products/2/comments/' . $id, ['author' => 'Intruder', 'content' => 'Changed.']);
        $delete = $this->postForm('/products/2/comments/' . $id . '/delete', []);
        $unknown = $this->get('/products/1/comments/999999/edit');

        foreach ([$edit, $update, $delete, $unknown] as $response) {
            self::assertSame(404, $response->statusCode);
            self::assertStringContainsString('Avis introuvable', $response->body);
        }
        self::assertSame([['Camille', 'Content of Camille']], $this->storedReviews());
    }

    public function test_review_content_is_escaped(): void
    {
        $this->insertReview(1, '<b>Camille</b>');

        $body = $this->get('/products/1')->body;

        self::assertStringNotContainsString('<b>Camille</b>', $body);
        self::assertStringContainsString('&lt;b&gt;Camille&lt;/b&gt;', $body);
    }

}
