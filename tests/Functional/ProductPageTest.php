<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Product\Money;
use App\Product\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Double\FakeProductProvider;
use Tests\Support\FunctionalTestCase;

/**
 * The product page, without its reviews (see ReviewsTest).
 */
final class ProductPageTest extends FunctionalTestCase
{
    private const string LONG_DESCRIPTION = 'Your perfect pack for everyday use and walks in the forest. Stash your laptop (up to 15 inches) in the padded sleeve, your everyday essentials in the main compartment, and keep the rest close at hand.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->givenProducts(new FakeProductProvider([
            new Product(1, 'Fjallraven - Foldsack No. 1 Backpack', self::LONG_DESCRIPTION, Money::fromCents(10995, 'EUR'), 'https://fakestoreapi.com/img/1.png'),
            new Product(2, 'Mens Casual T-Shirt', 'Slim-fitting style.', Money::fromCents(2230, 'EUR'), null),
            new Product(3, '<script>alert(1)</script>', 'Bad title.', Money::fromCents(100, 'EUR'), null),
        ]));
    }

    public function test_the_product_page_shows_the_title_the_price_the_description_and_a_large_image(): void
    {
        $response = $this->get('/products/1');

        self::assertSame(200, $response->statusCode);
        self::assertSame(1, substr_count($response->body, '<h1'));
        self::assertMatchesRegularExpression('#<h1[^>]*>Fjallraven - Foldsack No. 1 Backpack</h1>#', $response->body);
        self::assertStringContainsString("109,95\u{a0}€", $response->body);
        self::assertStringContainsString('in the main compartment, and keep the rest close at hand.', $response->body);
        self::assertMatchesRegularExpression('#<img src="https://fakestoreapi.com/img/1.png" alt="Fjallraven - Foldsack No. 1 Backpack" width="\d+" height="\d+"#', $response->body);
    }

    public function test_a_product_without_image_shows_a_placeholder(): void
    {
        $body = $this->get('/products/2')->body;

        self::assertStringNotContainsString('<img ', $body);
        self::assertStringContainsString('Image indisponible', $body);
    }

    public function test_the_breadcrumb_leads_back_to_the_catalogue(): void
    {
        $breadcrumb = self::fragment($this->get('/products/1')->body, '#<nav class="breadcrumb" aria-label="Fil d\'Ariane">.*?</nav>#s');

        self::assertStringContainsString('<ol class="breadcrumb__list">', $breadcrumb);
        self::assertStringContainsString('<a href="/">Catalogue</a>', $breadcrumb);
        self::assertStringContainsString('<li class="breadcrumb__separator" aria-hidden="true">/</li>', $breadcrumb);
        self::assertStringContainsString('<li aria-current="page">Fjallraven - Foldsack No. 1 Backpack</li>', $breadcrumb);
        // The current page is not a link.
        self::assertSame(1, substr_count($breadcrumb, '<a '));
    }

    public function test_the_page_has_its_own_title_description_and_canonical(): void
    {
        $body = $this->get('/products/1')->body;

        self::assertStringContainsString("<title>Fjallraven - Foldsack No. 1 Backpack, 109,95\u{a0}€ | Products Avenue</title>", $body);
        self::assertStringContainsString('<link rel="canonical" href="https://products-avenue.test/products/1">', $body);

        preg_match('#<meta name="description" content="([^"]*)">#', $body, $matches);
        $description = html_entity_decode($matches[1] ?? '', ENT_QUOTES);
        self::assertLessThanOrEqual(155, mb_strlen($description));
        self::assertStringStartsWith('Your perfect pack for everyday use', $description);
        self::assertStringEndsWith('…', $description);
    }

    public function test_the_product_page_is_never_cached(): void
    {
        // It holds a CSRF token of its own for each visitor: a shared cache would leak it.
        self::assertSame('no-store', $this->get('/products/1')->headers['Cache-Control']);
    }

    public function test_an_unknown_product_returns_a_not_found_page(): void
    {
        $response = $this->get('/products/9999');

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Produit introuvable', $response->body);
        self::assertStringContainsString('Retour au catalogue', $response->body);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $response->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'text' => ['/products/abc'];
        yield 'zero' => ['/products/0'];
        yield 'leading zero' => ['/products/01'];
    }

    #[DataProvider('invalidIds')]
    public function test_an_invalid_id_returns_a_not_found_page_without_calling_the_api(string $path): void
    {
        $response = $this->get($path);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Produit introuvable', $response->body);
        self::assertSame(0, $this->products->calls);
    }

    public function test_product_data_is_escaped_everywhere(): void
    {
        $body = $this->get('/products/3')->body;

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }
}
