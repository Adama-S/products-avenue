<?php

declare(strict_types=1);

namespace App\Product;

use App\Http\Client\HttpClientInterface;
use App\Http\Client\HttpResponse;
use App\Http\Client\HttpTransportException;

/**
 * Reads products from FakeStore API and turns them into Product objects (US-20).
 *
 * Adapter: the only class that knows FakeStore's URLs and JSON format. Switching to
 * another API means writing another ProductProviderInterface, nothing else changes.
 *
 * What can fail, and what the visitor sees:
 * - no response or a 5xx: one retry after 200 ms, then a 503 page;
 * - another 4xx: not retried (it would fail again), 503 page;
 * - unknown product (404, or FakeStore's 200 with an empty body): 404 page;
 * - invalid JSON or a product with a missing or mistyped field: 503 page,
 *   except in the list, where that product is skipped so the rest stays on sale.
 * Every error is logged with the URL, the HTTP status and the duration, never displayed.
 */
final readonly class HttpProductProvider implements ProductProviderInterface
{
    private string $baseUrl;

    /**
     * @param string $currency ISO 4217 code of the API prices: FakeStore does not give one
     * @param int $retryDelayMicroseconds wait before the single retry (200 ms; 0 in tests)
     */
    public function __construct(
        private HttpClientInterface $client,
        string $baseUrl,
        private string $currency,
        private int $retryDelayMicroseconds = 200_000,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * GET /products: the whole catalogue (FakeStore has no pagination).
     */
    public function getAll(): array
    {
        $url = $this->baseUrl . '/products';
        $response = $this->fetch($url);
        if ($response->statusCode === 404) {
            $this->fail($url, $response, 'product list not found');
        }

        $items = $this->decode($url, $response);
        if (!\is_array($items) || !array_is_list($items)) {
            $this->fail($url, $response, 'product list is not a JSON array');
        }

        return $this->toProducts($items, $url, $response);
    }

    /**
     * GET /products/{id}.
     */
    public function getById(int $id): Product
    {
        // Never put an unchecked id in the API URL.
        if ($id < 1) {
            throw new ProductNotFoundException(\sprintf('Product ids start at 1, got %d.', $id));
        }

        $url = $this->baseUrl . '/products/' . $id;
        $response = $this->fetch($url);
        // FakeStore answers an unknown id with 200 and an empty body; a 404 is handled the same way.
        if ($response->statusCode === 404 || trim($response->body) === '') {
            throw new ProductNotFoundException(\sprintf('No product with id %d.', $id));
        }

        return $this->toProduct($this->decode($url, $response))
            ?? $this->fail($url, $response, 'invalid product data');
    }

    /**
     * Calls the API, retrying once a failure that may be temporary.
     * Only a 200 or a 404 comes out of here.
     *
     * @throws ProductApiUnavailableException
     */
    private function fetch(string $url): HttpResponse
    {
        $response = $this->attempt($url);
        if ($response === null || $response->statusCode >= 500) {
            usleep($this->retryDelayMicroseconds);
            $response = $this->attempt($url);
        }

        if ($response === null || ($response->statusCode !== 200 && $response->statusCode !== 404)) {
            throw new ProductApiUnavailableException('Products API unavailable: GET ' . $url);
        }

        return $response;
    }

    /**
     * One call to the API; null when no response arrived. Failed attempts are logged.
     */
    private function attempt(string $url): ?HttpResponse
    {
        try {
            $response = $this->client->get($url);
        } catch (HttpTransportException $exception) {
            $this->log($url, null, $exception->durationMs, $exception->getMessage());

            return null;
        }

        if ($response->statusCode !== 200 && $response->statusCode !== 404) {
            $this->log($url, $response->statusCode, $response->durationMs, 'unexpected HTTP status');
        }

        return $response;
    }

    /**
     * @throws ProductApiUnavailableException when the body is not valid JSON
     */
    private function decode(string $url, HttpResponse $response): mixed
    {
        try {
            return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->fail($url, $response, 'invalid JSON: ' . $exception->getMessage());
        }
    }

    /**
     * Maps the list; an invalid product is skipped and logged: one broken product must not
     * take the whole catalogue down.
     *
     * @param list<mixed> $items
     *
     * @return list<Product>
     */
    private function toProducts(array $items, string $url, HttpResponse $response): array
    {
        $products = [];
        foreach ($items as $position => $item) {
            $product = $this->toProduct($item);
            if ($product === null) {
                $this->log($url, $response->statusCode, $response->durationMs, 'invalid product skipped at position ' . $position);

                continue;
            }
            $products[] = $product;
        }

        return $products;
    }

    /**
     * Maps one FakeStore product; null when a required field is missing or has the wrong type.
     */
    private function toProduct(mixed $item): ?Product
    {
        if (!\is_array($item)) {
            return null;
        }

        $id = $item['id'] ?? null;
        $title = $item['title'] ?? null;
        $description = $item['description'] ?? null;
        $price = $item['price'] ?? null;
        if (!\is_int($id) || $id < 1 || !\is_string($title) || trim($title) === '' || !\is_string($description)
            || !(\is_int($price) || \is_float($price)) || $price < 0) {
            return null;
        }

        return new Product($id, trim($title), $description, Money::fromDecimal($price, $this->currency), $this->imageUrl($item['image'] ?? null));
    }

    /**
     * The image is optional: a missing or unusable one shows a placeholder instead of a broken page.
     * Only HTTPS URLs are kept: anything else would be blocked by the CSP (img-src) or be mixed content.
     */
    private function imageUrl(mixed $image): ?string
    {
        return \is_string($image) && str_starts_with($image, 'https://') ? $image : null;
    }

    /**
     * @throws ProductApiUnavailableException always
     */
    private function fail(string $url, HttpResponse $response, string $reason): never
    {
        $this->log($url, $response->statusCode, $response->durationMs, $reason);

        throw new ProductApiUnavailableException(\sprintf('Products API unusable (%s): GET %s', $reason, $url));
    }

    private function log(string $url, ?int $statusCode, float $durationMs, string $reason): void
    {
        error_log(\sprintf(
            'Products API error: %s. GET %s, %s, %d ms',
            $reason,
            $url,
            $statusCode === null ? 'no response' : 'HTTP ' . $statusCode,
            (int) round($durationMs),
        ));
    }
}
