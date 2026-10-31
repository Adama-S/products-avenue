<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Comment\CommentRepository;
use App\Controller\HealthController;
use App\Controller\ProductController;
use App\Database\DatabaseUnavailableException;
use App\Database\PdoFactory;
use App\Http\Application;
use App\Http\Request;
use App\Http\Response;
use App\Product\Money;
use App\Product\Product;
use App\View\View;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Double\FakeProductProvider;
use Tests\Support\TestApplication;

final class CatalogueTest extends TestCase
{
    private PDO $pdo;

    private FakeProductProvider $products;

    private Application $application;

    private string $logFile;

    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        // The application logs the cause of each 503: kept out of the test output.
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
        $this->pdo = PdoFactory::fromEnvironment()->create();
        $this->pdo->exec('TRUNCATE TABLE comments');
        $this->products = FakeProductProvider::withIds(...range(1, 20));
        $this->application = TestApplication::create($this->products, $this->pdo);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        unlink($this->logFile);
    }

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
        $this->products = new FakeProductProvider([
            new Product(1, 'Backpack', 'A bag.', Money::fromCents(10995, 'EUR'), 'https://fakestoreapi.com/img/1.png'),
            FakeProductProvider::product(2),
        ]);
        $body = TestApplication::create($this->products, $this->pdo)->handle(new Request('GET', '/'))->body;

        self::assertMatchesRegularExpression('#<img src="https://fakestoreapi.com/img/1.png" alt="Backpack" width="\d+" height="\d+" loading="lazy"#', $body);
        // Product 2 has no image: a placeholder, never a broken <img>.
        self::assertSame(1, substr_count($body, '<img '));
        self::assertStringContainsString('Image indisponible', $body);
    }

    public function test_each_card_shows_its_review_count(): void
    {
        $comments = new CommentRepository(fn(): PDO => $this->pdo);
        $comments->create(1, 'Camille', 'Bien.');
        $comments->create(1, 'Inès', 'Très bien.');
        $comments->create(3, 'Léa', 'Parfait.');

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

    public function test_the_first_page_has_a_canonical_without_parameters(): void
    {
        $body = $this->get('/')->body;

        self::assertStringContainsString('<title>Catalogue | Products Avenue</title>', $body);
        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/">', $body);
        self::assertSame(1, substr_count($body, '<h1'));
        self::assertStringContainsString('<meta name="description" content="', $body);
    }

    public function test_every_page_declares_the_favicon(): void
    {
        foreach (['/', '/?page=99'] as $uri) {
            self::assertStringContainsString('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', $this->get($uri)->body);
        }
        self::assertFileExists(\dirname(__DIR__, 2) . '/public/favicon.svg');
    }

    public function test_the_pagination_has_no_previous_link_on_the_first_page(): void
    {
        $pagination = $this->pagination($this->get('/')->body);

        self::assertStringContainsString('aria-label="Pagination"', $pagination);
        self::assertStringContainsString('<span class="pagination__current" aria-current="page">1</span>', $pagination);
        self::assertStringContainsString('href="/?page=2"', $pagination);
        self::assertStringContainsString('rel="next"', $pagination);
        self::assertStringNotContainsString('rel="prev"', $pagination);
    }

    public function test_the_pagination_has_no_next_link_on_the_last_page(): void
    {
        $pagination = $this->pagination($this->get('/?page=2')->body);

        self::assertStringContainsString('<span class="pagination__current" aria-current="page">2</span>', $pagination);
        self::assertStringContainsString('<a class="pagination__link" href="/" rel="prev">', $pagination);
        self::assertStringNotContainsString('rel="next"', $pagination);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'after the last page' => ['/?page=99'];
        yield 'zero' => ['/?page=0'];
        yield 'text' => ['/?page=abc'];
        yield 'negative' => ['/?page=-1'];
        yield 'array' => ['/?page[]=1'];
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

    public function test_an_unknown_url_returns_a_not_found_page(): void
    {
        $response = $this->get('/unknown');

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Page introuvable', $response->body);
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
        $this->products = FakeProductProvider::withIds(...range(1, 45));
        $application = TestApplication::create($this->products, $this->pdo);

        $pagination = $this->pagination($application->handle(new Request('GET', '/', ['page' => '2', 'per_page' => '20']))->body);

        self::assertStringContainsString('href="/?per_page=20"', $pagination);
        self::assertStringContainsString('href="/?page=3&amp;per_page=20"', $pagination);
    }

    public function test_an_unknown_page_size_falls_back_to_the_default_without_error(): void
    {
        $response = $this->get('/?per_page=100000');

        self::assertSame(200, $response->statusCode);
        self::assertSame(10, substr_count($response->body, 'class="product-card"'));
        self::assertStringNotContainsString('per_page=100000', $response->body);
    }

    public function test_the_page_size_form_works_without_javascript(): void
    {
        $body = $this->get('/')->body;

        self::assertMatchesRegularExpression('#<form [^>]*method="get" action="/"#', $body);
        self::assertStringContainsString('<label for="per-page"', $body);
        self::assertStringContainsString('<select id="per-page" name="per_page"', $body);
        self::assertStringContainsString('<button', $body);
    }

    public function test_the_canonical_never_contains_the_page_size(): void
    {
        $body = $this->get('/?page=2&per_page=10')->body;

        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/?page=2">', $body);
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

        $response = $this->application->handle(new Request('GET', '/', headers: ['if-none-match' => $etag]));

        self::assertSame(304, $response->statusCode);
        self::assertSame('', $response->body);
        self::assertSame($etag, $response->headers['ETag']);
    }

    public function test_the_etag_is_compared_the_weak_way(): void
    {
        $etag = $this->get('/')->headers['ETag'];
        $strongForm = substr($etag, 2);

        $response = $this->application->handle(new Request('GET', '/', headers: ['if-none-match' => '"other", ' . $strongForm]));

        self::assertSame(304, $response->statusCode);
    }

    public function test_a_changed_catalogue_returns_the_new_page(): void
    {
        $response = $this->application->handle(new Request('GET', '/', headers: ['if-none-match' => '"outdated"']));

        self::assertSame(200, $response->statusCode);
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
            self::assertStringContainsString('img-src \'self\' https://fakestoreapi.com', $headers['Content-Security-Policy']);
            self::assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
            self::assertSame('nosniff', $headers['X-Content-Type-Options']);
            self::assertSame('DENY', $headers['X-Frame-Options']);
        }
    }

    public function test_product_data_from_the_api_is_escaped(): void
    {
        $this->products = new FakeProductProvider([
            new Product(1, '<script>alert(1)</script>', 'A bag.', Money::fromCents(100, 'EUR'), null),
        ]);
        $body = TestApplication::create($this->products, $this->pdo)->handle(new Request('GET', '/'))->body;

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
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
        $view = new View(\dirname(__DIR__, 2) . '/templates', TestApplication::APP_URL);
        $comments = new CommentRepository(static fn(): PDO => throw new DatabaseUnavailableException('MySQL is down.'));
        $application = new Application(new ProductController($this->products, $comments, $view, 10), new HealthController(PdoFactory::fromEnvironment()), $view);

        $response = $application->handle(new Request('GET', '/'));

        self::assertSame(503, $response->statusCode);
        self::assertStringNotContainsString('MySQL is down', $response->body);
        self::assertStringContainsString('MySQL is down', (string) file_get_contents($this->logFile));
    }

    public function test_the_health_check_reports_the_database(): void
    {
        $response = $this->get('/health');

        self::assertSame(200, $response->statusCode);
        self::assertSame('{"status":"ok","database":"up"}', $response->body);
    }

    private function get(string $uri): Response
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return $this->application->handle(new Request('GET', $path, $query));
    }

    private function pagination(string $body): string
    {
        preg_match('#<nav class="pagination".*?</nav>#s', $body, $matches);

        return $matches[0] ?? self::fail('The page has no pagination.');
    }
}
