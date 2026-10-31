<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Http\Application;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Double\FakeProductProvider;
use Tests\Support\FunctionalTestCase;
use Tests\Support\TestApplication;

final class ReviewsTest extends FunctionalTestCase
{
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->application = TestApplication::create(FakeProductProvider::withIds(1, 2, 3), $this->pdo);
    }

    public function test_a_product_without_reviews_invites_the_first_one(): void
    {
        $body = $this->get('/products/1')->body;

        self::assertStringContainsString("Aucun avis pour l'instant.", $body);
        self::assertStringContainsString('Soyez la première personne à donner le vôtre.', $body);
        self::assertStringContainsString("Pas encore d'avis", $body);
        self::assertStringContainsString('<h2 id="avis" class="reviews__title">Avis clients</h2>', $body);
    }

    public function test_reviews_are_listed_newest_first_with_french_dates_in_paris_time(): void
    {
        $this->insert(1, 'Léa', '2026-10-28 08:10:00');
        $this->insert(1, 'Camille', '2026-10-30 23:26:00');
        $this->insert(2, 'Other product', '2026-10-31 10:00:00');

        $body = $this->get('/products/1')->body;

        self::assertMatchesRegularExpression('#Camille.*Léa#s', $body);
        self::assertStringNotContainsString('Other product', $body);
        self::assertStringContainsString('31 octobre 2026 à 00:26', $body);
        self::assertStringContainsString('28 octobre 2026 à 09:10', $body);
        self::assertStringContainsString('Avis clients <span class="reviews__count">(2)</span>', $body);
        self::assertStringContainsString('<a class="product__reviews-link" href="#avis">Lire les 2 avis ↓</a>', $body);
    }

    public function test_only_an_edited_review_is_marked_as_modified(): void
    {
        $this->insert(1, 'Inès', '2026-10-30 17:45:00', '2026-10-31 09:00:00');
        $this->insert(1, 'Léa', '2026-10-28 08:10:00');

        $body = $this->get('/products/1')->body;

        self::assertSame(1, substr_count($body, ' · modifié'));
        self::assertStringContainsString('30 octobre 2026 à 18:45 · modifié', $body);
    }

    public function test_reviews_are_paginated_ten_per_page(): void
    {
        for ($day = 1; $day <= 12; ++$day) {
            $this->insert(1, 'Author ' . $day, \sprintf('2026-10-%02d 10:00:00', $day));
        }

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

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReviewPages(): iterable
    {
        yield 'after the last page' => ['/products/1?comments_page=2'];
        yield 'zero' => ['/products/1?comments_page=0'];
        yield 'text' => ['/products/1?comments_page=abc'];
    }

    #[DataProvider('invalidReviewPages')]
    public function test_an_invalid_review_page_returns_a_not_found_page(string $uri): void
    {
        $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        $response = $this->get($uri);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Page introuvable', $response->body);
    }

    public function test_a_valid_review_is_published_with_post_redirect_get(): void
    {
        $response = $this->post('/products/1/comments', ['author' => '  Camille ', 'content' => "Très pratique.\r\nBien rembourré."]);

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
        $response = $this->post('/products/1/comments', ['author' => ' ', 'content' => '']);

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

        $response = $this->post('/products/1/comments', ['author' => 'Camille', 'content' => $content]);

        self::assertSame(422, $response->statusCode);
        self::assertStringContainsString('Le formulaire contient 1 erreur.', $response->body);
        self::assertStringContainsString("1\u{202f}000 caractères maximum.", $response->body);
        self::assertStringContainsString('value="Camille"', $response->body);
        self::assertStringContainsString('>' . $content . '</textarea>', $response->body);
        self::assertSame([], $this->storedReviews());
    }

    public function test_a_review_that_is_not_utf8_is_refused_without_a_server_error(): void
    {
        $response = $this->post('/products/1/comments', ['author' => 'Camille', 'content' => "Tr\xE8s bien."]);

        self::assertSame(422, $response->statusCode);
        self::assertStringContainsString('Ce texte contient des caractères non valides.', $response->body);
        // Shown back through e(): the invalid bytes are replaced, the page stays valid UTF-8.
        self::assertTrue(mb_check_encoding($response->body, 'UTF-8'));
        self::assertSame([], $this->storedReviews());
    }

    public function test_a_review_on_an_unknown_product_is_refused(): void
    {
        $response = $this->post('/products/9999/comments', ['author' => 'Camille', 'content' => 'Bien.']);

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
        yield 'empty token' => [['csrf_token' => '']];
        yield 'missing token' => [[]];
    }

    /**
     * @param array<string, string> $token
     */
    #[DataProvider('forgedTokens')]
    public function test_a_form_without_the_session_token_is_refused(array $token): void
    {
        $this->get('/products/1');

        $response = $this->application->handle(new Request('POST', '/products/1/comments', post: $token + ['author' => 'Camille', 'content' => 'Bien.']));

        self::assertSame(403, $response->statusCode);
        self::assertStringContainsString('Formulaire expiré', $response->body);
        self::assertSame([], $this->storedReviews());
    }

    public function test_every_form_carries_the_csrf_token(): void
    {
        $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        $body = $this->get('/products/1')->body;

        // The review form and the delete form of the review.
        self::assertSame(2, preg_match_all('#<input type="hidden" name="csrf_token" value="[0-9a-f]{64}">#', $body));
    }

    public function test_the_edit_form_is_prefilled_and_not_indexed(): void
    {
        $id = $this->insert(1, 'Inès', '2026-10-30 17:45:00');

        $response = $this->get('/products/1/comments/' . $id . '/edit?comments_page=1');

        self::assertSame(200, $response->statusCode);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $response->body);
        self::assertStringContainsString('<h1 class="edit-review__title">Modifier votre avis</h1>', $response->body);
        self::assertStringContainsString('Publié le 30 octobre 2026 à 18:45', $response->body);
        self::assertStringContainsString('value="Inès"', $response->body);
        self::assertStringContainsString('>Content of Inès</textarea>', $response->body);
        self::assertMatchesRegularExpression('#<input type="hidden" name="csrf_token" value="[0-9a-f]{64}">#', $response->body);
    }

    public function test_the_edit_page_has_a_three_level_breadcrumb(): void
    {
        $id = $this->insert(1, 'Inès', '2026-10-30 17:45:00');

        $body = $this->get('/products/1/comments/' . $id . '/edit')->body;

        self::assertMatchesRegularExpression('#<a href="/">Catalogue</a>.*<a href="/products/1">Product 1</a>.*<li aria-current="page">Modifier un avis</li>#s', $body);
    }

    public function test_an_update_returns_to_the_review_page_of_origin(): void
    {
        for ($day = 1; $day <= 12; ++$day) {
            $id = $this->insert(1, 'Author ' . $day, \sprintf('2026-10-%02d 10:00:00', $day));
        }
        $oldest = $id - 11;

        $response = $this->post('/products/1/comments/' . $oldest, ['author' => 'Author 1', 'content' => 'Changed my mind.', 'comments_page' => '2']);

        self::assertSame(303, $response->statusCode);
        self::assertSame('/products/1?comments_page=2&review=updated#avis', $response->headers['Location']);
        $page = $this->get('/products/1?comments_page=2&review=updated')->body;
        self::assertStringContainsString('Votre avis est modifié.', $page);
        self::assertStringContainsString('Changed my mind.', $page);
        self::assertStringContainsString(' · modifié', $page);
    }

    public function test_an_invalid_page_of_origin_falls_back_to_the_first_page(): void
    {
        $id = $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        $response = $this->post('/products/1/comments/' . $id, ['author' => 'Camille', 'content' => 'Changed.', 'comments_page' => 'abc']);

        self::assertSame('/products/1?review=updated#avis', $response->headers['Location']);
    }

    public function test_an_invalid_update_shows_the_edit_form_again(): void
    {
        $id = $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        $response = $this->post('/products/1/comments/' . $id, ['author' => 'Camille B.', 'content' => '   ']);

        self::assertSame(422, $response->statusCode);
        self::assertStringContainsString('Modifier votre avis', $response->body);
        self::assertStringContainsString('value="Camille B."', $response->body);
        self::assertStringContainsString('Écrivez votre avis.', $response->body);
        self::assertSame([['Camille', 'Content of Camille']], $this->storedReviews());
    }

    public function test_a_deletion_returns_to_the_last_page_that_still_exists(): void
    {
        for ($day = 1; $day <= 11; ++$day) {
            $id = $this->insert(1, 'Author ' . $day, \sprintf('2026-10-%02d 10:00:00', $day));
        }
        $onlyReviewOfPageTwo = $id - 10;

        $response = $this->post('/products/1/comments/' . $onlyReviewOfPageTwo . '/delete', ['comments_page' => '2']);

        self::assertSame(303, $response->statusCode);
        self::assertSame('/products/1?review=deleted#avis', $response->headers['Location']);
        self::assertCount(10, $this->storedReviews());
        self::assertStringContainsString('Votre avis est supprimé.', $this->get('/products/1?review=deleted')->body);
    }

    public function test_a_review_is_deleted_by_post_only(): void
    {
        $id = $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        self::assertSame(404, $this->get('/products/1/comments/' . $id . '/delete')->statusCode);
        self::assertCount(1, $this->storedReviews());
    }

    public function test_the_delete_form_asks_for_a_confirmation(): void
    {
        $id = $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        $body = $this->get('/products/1')->body;

        self::assertMatchesRegularExpression('#<form [^>]*method="post" action="/products/1/comments/' . $id . '/delete" data-confirm="[^"]+"#', $body);
        self::assertStringContainsString('<script src="/assets/app.js" defer></script>', $body);
    }

    public function test_a_review_of_another_product_is_not_found(): void
    {
        $id = $this->insert(1, 'Camille', '2026-10-01 10:00:00');

        $edit = $this->get('/products/2/comments/' . $id . '/edit');
        $update = $this->post('/products/2/comments/' . $id, ['author' => 'Intruder', 'content' => 'Changed.']);
        $delete = $this->post('/products/2/comments/' . $id . '/delete', []);

        foreach ([$edit, $update, $delete] as $response) {
            self::assertSame(404, $response->statusCode);
            self::assertStringContainsString('Avis introuvable', $response->body);
        }
        self::assertSame([['Camille', 'Content of Camille']], $this->storedReviews());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReviewIds(): iterable
    {
        yield 'unknown' => ['/products/1/comments/999999/edit'];
        yield 'text' => ['/products/1/comments/abc/edit'];
        yield 'zero' => ['/products/1/comments/0/edit'];
    }

    #[DataProvider('invalidReviewIds')]
    public function test_an_invalid_review_id_is_not_found(string $path): void
    {
        self::assertStringContainsString('Avis introuvable', $this->get($path)->body);
    }

    public function test_review_content_is_escaped(): void
    {
        $this->insert(1, '<b>Camille</b>', '2026-10-01 10:00:00');

        $body = $this->get('/products/1')->body;

        self::assertStringNotContainsString('<b>Camille</b>', $body);
        self::assertStringContainsString('&lt;b&gt;Camille&lt;/b&gt;', $body);
    }

    public function test_the_review_field_is_ready_for_the_character_counter(): void
    {
        $body = $this->get('/products/1')->body;

        // No maxlength: the server must be able to refuse and explain a too-long review.
        self::assertStringContainsString('data-max-length="1000"', $body);
        self::assertDoesNotMatchRegularExpression('#<textarea[^>]*maxlength#', $body);
    }

    public function test_the_catalogue_card_counts_a_new_review(): void
    {
        $this->post('/products/1/comments', ['author' => 'Camille', 'content' => 'Bien.']);

        self::assertMatchesRegularExpression('#Product 1<.*?1 avis#s', $this->get('/')->body);
    }

    private function get(string $uri): Response
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return $this->application->handle(new Request('GET', $path, $query));
    }

    /**
     * Posts a form with the CSRF token of the session, as a browser would.
     *
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields): Response
    {
        return $this->application->handle(new Request('POST', $path, post: ['csrf_token' => $this->csrfToken()] + $fields));
    }

    private function csrfToken(): string
    {
        preg_match('#name="csrf_token" value="([0-9a-f]{64})"#', $this->get('/products/1')->body, $matches);

        return $matches[1] ?? self::fail('The product page has no CSRF token.');
    }

    private function insert(int $productId, string $author, string $createdAt, ?string $updatedAt = null): int
    {
        $statement = $this->pdo->prepare('INSERT INTO comments (product_external_id, author, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$productId, $author, 'Content of ' . $author, $createdAt, $updatedAt]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array{string, string}>
     */
    private function storedReviews(): array
    {
        $statement = $this->pdo->query('SELECT author, content FROM comments ORDER BY id');
        self::assertNotFalse($statement);

        return array_values(array_map(static fn(array $row): array => [(string) $row['author'], (string) $row['content']], $statement->fetchAll()));
    }
}
