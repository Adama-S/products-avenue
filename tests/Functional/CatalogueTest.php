<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Comment\CommentRepository;
use App\Database\DatabaseUnavailableException;
use App\Product\Money;
use App\Product\Product;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Double\FakeProductProvider;
use Tests\Support\FunctionalTestCase;
use Tests\Support\TestApplication;

/**
 * The catalogue: products 1 to 20, 10 per page by default.
 */
final class CatalogueTest extends FunctionalTestCase
{
    private const string PAGINATION = '#<nav class="pagination".*?</nav>#s';

    public function test_the_first_page_lists_ten_products_with_title_image_and_price(): void
    {
        $response = $this->get('/');

        self::assertSame(200, $response->statusCode);
        self::assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame(10, substr_count($response->body, 'class="product-card"'));
        self::assertStringContainsString('Product 1<', $response->body);
        self::assertStringContainsString('Product 10<', $response->body);
        self::assertStringNotContainsString('Product 11<', $response->body);
        self::assertStringContainsString("10,01\u{a0}€", $response->body);
        self::assertStringContainsString('href="/products/1"', $response->body);
        self::assertStringContainsString('20 produits · page 1 sur 2', $response->body);
    }

    public function test_a_product_image_is_lazy_loaded_with_fixed_dimensions_and_a_text_alternative(): void
    {
        $this->givenProducts(new FakeProductProvider([
            new Product(1, 'Backpack', 'A bag.', Money::fromCents(10995, 'EUR'), 'https://fakestoreapi.com/img/1.png'),
            FakeProductProvider::product(2),
        ]));

        $body = $this->get('/')->body;

        self::assertMatchesRegularExpression('#<img src="https://fakestoreapi.com/img/1.png" alt="Backpack" width="\d+" height="\d+" loading="lazy"#', $body);
        // Product 2 has no image: a placeholder, never a broken <img>.
        self::assertSame(1, substr_count($body, '<img '));
        self::assertStringContainsString('Image indisponible', $body);
    }

    public function test_each_card_shows_its_review_count(): void
    {
        $this->insertReview(1, 'Camille');
        $this->insertReview(1, 'Inès');
        $this->insertReview(3, 'Léa');

        $body = $this->get('/')->body;

        self::assertMatchesRegularExpression('#Product 1<.*?2 avis#s', $body);
        self::assertMatchesRegularExpression('#Product 2<.*?0 avis#s', $body);
        self::assertMatchesRegularExpression('#Product 3<.*?1 avis#s', $body);
    }

    public function test_the_second_page_lists_the_next_products(): void
    {
        $body = $this->get('/?page=2')->body;

        self::assertStringContainsString('Product 11<', $body);
        self::assertStringContainsString('Product 20<', $body);
        self::assertStringNotContainsString('Product 10<', $body);
        self::assertStringContainsString('<title>Catalogue, page 2 | Products Avenue</title>', $body);
        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/?page=2">', $body);
    }

    public function test_the_first_page_has_its_own_title_canonical_and_description(): void
    {
        $body = $this->get('/')->body;

        self::assertStringContainsString('<title>Catalogue | Products Avenue</title>', $body);
        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/">', $body);
        self::assertSame(1, substr_count($body, '<h1'));
        self::assertStringContainsString('<meta name="description" content="', $body);
    }

    public function test_the_pagination_has_no_previous_link_on_the_first_page(): void
    {
        $pagination = self::fragment($this->get('/')->body, self::PAGINATION);

        self::assertStringContainsString('aria-label="Pagination"', $pagination);
        self::assertStringContainsString('<span class="pagination__current" aria-current="page">1</span>', $pagination);
        self::assertStringContainsString('href="/?page=2"', $pagination);
        self::assertStringContainsString('rel="next"', $pagination);
        self::assertStringNotContainsString('rel="prev"', $pagination);
    }

    public function test_the_pagination_has_no_next_link_on_the_last_page(): void
    {
        $pagination = self::fragment($this->get('/?page=2')->body, self::PAGINATION);

        self::assertStringContainsString('<span class="pagination__current" aria-current="page">2</span>', $pagination);
        self::assertStringContainsString('<a class="pagination__link" href="/" rel="prev">', $pagination);
        self::assertStringNotContainsString('rel="next"', $pagination);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPages(): iterable
    {
        // Every invalid form of ?page is unit-tested in PaginationTest.
        yield 'after the last page' => ['/?page=99'];
        yield 'not a single value' => ['/?page[]=1'];
    }

    #[DataProvider('invalidPages')]
    public function test_an_invalid_page_returns_a_not_found_page(string $uri): void
    {
        $response = $this->get($uri);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Page introuvable', $response->body);
        self::assertStringContainsString('Retour au catalogue', $response->body);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $response->body);
        self::assertSame('no-store', $response->headers['Cache-Control']);
    }

    public function test_the_visitor_can_show_twenty_products_per_page(): void
    {
        $body = $this->get('/?per_page=20')->body;

        self::assertSame(20, substr_count($body, 'class="product-card"'));
        self::assertStringContainsString('<option value="20" selected>', $body);
        self::assertStringContainsString('20 produits · page 1 sur 1', $body);
    }

    public function test_the_chosen_page_size_is_kept_in_the_pagination_links(): void
    {
        $this->givenProducts(FakeProductProvider::withIds(...range(1, 45)));

        $pagination = self::fragment($this->get('/?page=2&per_page=20')->body, self::PAGINATION);

        self::assertStringContainsString('href="/?per_page=20"', $pagination);
        self::assertStringContainsString('href="/?page=3&amp;per_page=20"', $pagination);
        // The canonical never carries per_page: one indexed URL per page of the catalogue.
        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/?page=2">', $this->get('/?page=2&per_page=20')->body);
    }

    public function test_an_unknown_page_size_falls_back_to_the_default_without_error(): void
    {
        $response = $this->get('/?per_page=100000');

        self::assertSame(200, $response->statusCode);
        self::assertSame(10, substr_count($response->body, 'class="product-card"'));
        self::assertStringNotContainsString('per_page=100000', $response->body);
    }

    public function test_the_catalogue_can_be_cached_by_browsers_and_proxies(): void
    {
        $response = $this->get('/');

        self::assertSame('public, max-age=300', $response->headers['Cache-Control']);
        // Weak: the same page, gzip-compressed by Apache or not, has the same ETag.
        self::assertMatchesRegularExpression('#^W/"[0-9a-f]{32}"$#', $response->headers['ETag']);
    }

    public function test_an_unchanged_catalogue_returns_304_without_a_body(): void
    {
        $etag = $this->get('/')->headers['ETag'];

        $response = $this->get('/', ['if-none-match' => $etag]);

        self::assertSame(304, $response->statusCode);
        self::assertSame('', $response->body);
        self::assertSame($etag, $response->headers['ETag']);
    }

    public function test_the_etag_is_compared_the_weak_way(): void
    {
        $strongForm = substr($this->get('/')->headers['ETag'], 2);

        self::assertSame(304, $this->get('/', ['if-none-match' => '"other", ' . $strongForm])->statusCode);
    }

    public function test_the_catalogue_opens_no_session_and_sets_no_cookie(): void
    {
        $response = $this->get('/');

        self::assertArrayNotHasKey('Set-Cookie', $response->headers);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function test_security_headers_are_sent_on_every_response(): void
    {
        foreach (['/', '/?page=99'] as $uri) {
            $headers = $this->get($uri)->headers;

            self::assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
            self::assertStringContainsString("img-src 'self' https://fakestoreapi.com", $headers['Content-Security-Policy']);
            self::assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
            self::assertSame('nosniff', $headers['X-Content-Type-Options']);
            self::assertSame('DENY', $headers['X-Frame-Options']);
        }
    }

    public function test_an_api_outage_without_a_copy_returns_a_service_unavailable_page(): void
    {
        $this->products->goDown();

        $response = $this->get('/');

        self::assertSame(503, $response->statusCode);
        self::assertStringContainsString('Le catalogue est momentanément indisponible', $response->body);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $response->body);
        self::assertSame('no-store', $response->headers['Cache-Control']);
    }

    public function test_a_database_outage_returns_a_service_unavailable_page(): void
    {
        $downDatabase = new CommentRepository(static fn(): PDO => throw new DatabaseUnavailableException('MySQL is down.'));
        $this->application = TestApplication::create($this->products, $downDatabase);

        $response = $this->get('/');

        self::assertSame(503, $response->statusCode);
        self::assertStringNotContainsString('MySQL is down', $response->body);
        self::assertStringContainsString('MySQL is down', $this->logs());
    }

}
