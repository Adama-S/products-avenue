<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use App\Http\Client\HttpResponse;
use App\Http\Client\HttpTransportException;
use App\Product\HttpProductProvider;
use App\Product\Product;
use App\Product\ProductApiUnavailableException;
use App\Product\ProductNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Double\FakeHttpClient;

final class HttpProductProviderTest extends TestCase
{
    private const string BASE_URL = 'https://fakestoreapi.test';

    private string $logFile;

    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        // error_log() writes to this file during the test, so the logs can be checked.
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        unlink($this->logFile);
    }

    public function test_get_all_returns_the_products_of_the_api(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json([self::apiProduct(1), self::apiProduct(2)]));

        $products = $this->provider($client)->getAll();

        self::assertSame([self::BASE_URL . '/products'], $client->requestedUrls);
        self::assertCount(2, $products);
        self::assertSame([1, 2], array_map(static fn(Product $product): int => $product->id, $products));
    }

    public function test_an_api_product_is_mapped_to_a_typed_product(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json(self::apiProduct(1)));

        $product = $this->provider($client)->getById(1);

        self::assertSame([self::BASE_URL . '/products/1'], $client->requestedUrls);
        self::assertSame(1, $product->id);
        self::assertSame('Fjallraven - Foldsack No. 1 Backpack', $product->title);
        self::assertSame('Your perfect pack for everyday use.', $product->description);
        self::assertSame(10995, $product->price->amountInCents);
        self::assertSame('EUR', $product->price->currency);
        self::assertSame('https://fakestoreapi.com/img/81fPKd-2AYL._AC_SL1500_t.png', $product->imageUrl);
    }

    public function test_the_price_uses_the_configured_currency_and_accepts_integers(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json(['price' => 695] + self::apiProduct(5)));

        $price = $this->provider($client, currency: 'USD')->getById(5)->price;

        self::assertSame(69500, $price->amountInCents);
        self::assertSame('USD', $price->currency);
    }

    public function test_a_trailing_slash_in_the_base_url_is_ignored(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json(self::apiProduct(1)));

        new HttpProductProvider($client, self::BASE_URL . '/', 'EUR', 0)->getById(1);

        self::assertSame([self::BASE_URL . '/products/1'], $client->requestedUrls);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableImages(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'not a string' => [42];
        yield 'not https' => ['http://fakestoreapi.com/img/1.png'];
        yield 'script url' => ['javascript:alert(1)'];
    }

    #[DataProvider('unusableImages')]
    public function test_an_unusable_image_becomes_no_image(mixed $image): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json(['image' => $image] + self::apiProduct(1)));

        self::assertNull($this->provider($client)->getById(1)->imageUrl);
    }

    public function test_an_unknown_product_answered_with_an_empty_body_is_not_found(): void
    {
        // What FakeStore really does for an unknown id (checked on 2026-10-31): 200 with an empty body.
        $client = new FakeHttpClient(FakeHttpClient::status(200));

        $this->expectException(ProductNotFoundException::class);

        $this->provider($client)->getById(9999);
    }

    public function test_an_unknown_product_answered_with_a_404_is_not_found(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::status(404));

        $this->expectException(ProductNotFoundException::class);

        $this->provider($client)->getById(9999);
    }

    public function test_a_non_positive_id_is_not_found_without_calling_the_api(): void
    {
        $client = new FakeHttpClient();

        try {
            $this->provider($client)->getById(0);
            self::fail('A non-positive id must be refused.');
        } catch (ProductNotFoundException) {
            self::assertSame([], $client->requestedUrls);
        }
    }

    /**
     * @return iterable<string, array{HttpResponse|HttpTransportException}>
     */
    public static function transientFailures(): iterable
    {
        yield 'timeout or connection failure' => [FakeHttpClient::timeout()];
        yield 'server error' => [FakeHttpClient::status(500)];
        yield 'bad gateway' => [FakeHttpClient::status(502)];
    }

    #[DataProvider('transientFailures')]
    public function test_a_transient_failure_is_retried_once(HttpResponse|HttpTransportException $failure): void
    {
        $client = new FakeHttpClient($failure, FakeHttpClient::json(self::apiProduct(1)));

        $product = $this->provider($client)->getById(1);

        self::assertSame(1, $product->id);
        self::assertCount(2, $client->requestedUrls);
    }

    #[DataProvider('transientFailures')]
    public function test_a_second_failure_makes_the_api_unavailable(HttpResponse|HttpTransportException $failure): void
    {
        // A third reply is queued: it must never be requested.
        $client = new FakeHttpClient($failure, $failure, FakeHttpClient::json(self::apiProduct(1)));

        try {
            $this->provider($client)->getById(1);
            self::fail('Two failures in a row must make the API unavailable.');
        } catch (ProductApiUnavailableException) {
            self::assertCount(2, $client->requestedUrls);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function clientErrors(): iterable
    {
        yield 'bad request' => [400];
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
        yield 'too many requests' => [429];
        yield 'redirect' => [301];
    }

    #[DataProvider('clientErrors')]
    public function test_another_client_error_is_not_retried(int $statusCode): void
    {
        $client = new FakeHttpClient(FakeHttpClient::status($statusCode), FakeHttpClient::json(self::apiProduct(1)));

        try {
            $this->provider($client)->getById(1);
            self::fail('A client error must make the API unavailable.');
        } catch (ProductApiUnavailableException) {
            self::assertCount(1, $client->requestedUrls);
        }
    }

    public function test_invalid_json_makes_the_api_unavailable(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::status(200, '{"id": 1, "title": '));

        $this->expectException(ProductApiUnavailableException::class);

        $this->provider($client)->getById(1);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidProducts(): iterable
    {
        $product = self::apiProduct(1);

        yield 'missing id' => [array_diff_key($product, ['id' => true])];
        yield 'id as a string' => [['id' => '1'] + $product];
        yield 'missing title' => [array_diff_key($product, ['title' => true])];
        yield 'empty title' => [['title' => '  '] + $product];
        yield 'missing description' => [array_diff_key($product, ['description' => true])];
        yield 'missing price' => [array_diff_key($product, ['price' => true])];
        yield 'price as a string' => [['price' => '109.95'] + $product];
        yield 'negative price' => [['price' => -1] + $product];
    }

    /**
     * @param array<string, mixed> $apiProduct
     */
    #[DataProvider('invalidProducts')]
    public function test_an_invalid_product_makes_its_page_unavailable(array $apiProduct): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json($apiProduct));

        $this->expectException(ProductApiUnavailableException::class);

        $this->provider($client)->getById(1);
    }

    public function test_an_invalid_product_is_skipped_in_the_list_and_logged(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::json([
            self::apiProduct(1),
            ['price' => 'free'] + self::apiProduct(2),
            'not a product',
            self::apiProduct(3),
        ]));

        $products = $this->provider($client)->getAll();

        self::assertSame([1, 3], array_map(static fn(Product $product): int => $product->id, $products));
        self::assertStringContainsString('invalid product skipped', $this->logs());
    }

    /**
     * @return iterable<string, array{HttpResponse}>
     */
    public static function unusableLists(): iterable
    {
        yield 'empty body' => [FakeHttpClient::status(200)];
        yield 'object instead of a list' => [FakeHttpClient::json(['id' => 1])];
        yield 'scalar' => [FakeHttpClient::json(true)];
        yield 'not found' => [FakeHttpClient::status(404)];
    }

    #[DataProvider('unusableLists')]
    public function test_an_unusable_list_makes_the_catalogue_unavailable(HttpResponse $response): void
    {
        $client = new FakeHttpClient($response);

        $this->expectException(ProductApiUnavailableException::class);

        $this->provider($client)->getAll();
    }

    public function test_each_error_is_logged_with_the_url_the_status_and_the_duration(): void
    {
        $client = new FakeHttpClient(FakeHttpClient::status(503), FakeHttpClient::timeout());

        try {
            $this->provider($client)->getAll();
            self::fail('The API must be unavailable.');
        } catch (ProductApiUnavailableException) {
            $logs = $this->logs();
            self::assertStringContainsString('GET ' . self::BASE_URL . '/products', $logs);
            self::assertStringContainsString('HTTP 503', $logs);
            self::assertStringContainsString('12 ms', $logs);
            self::assertStringContainsString('no response', $logs);
            self::assertStringContainsString('5000 ms', $logs);
        }
    }

    private function provider(FakeHttpClient $client, string $currency = 'EUR'): HttpProductProvider
    {
        // No delay before the retry: the tests stay fast.
        return new HttpProductProvider($client, self::BASE_URL, $currency, 0);
    }

    /**
     * A product as FakeStore sends it.
     *
     * @return array<string, mixed>
     */
    private static function apiProduct(int $id): array
    {
        return [
            'id' => $id,
            'title' => 'Fjallraven - Foldsack No. 1 Backpack',
            'price' => 109.95,
            'description' => 'Your perfect pack for everyday use.',
            'category' => "men's clothing",
            'image' => 'https://fakestoreapi.com/img/81fPKd-2AYL._AC_SL1500_t.png',
            'rating' => ['rate' => 3.9, 'count' => 120],
        ];
    }

    private function logs(): string
    {
        return (string) file_get_contents($this->logFile);
    }
}
