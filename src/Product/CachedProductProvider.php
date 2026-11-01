<?php

declare(strict_types=1);

namespace App\Product;

use App\Cache\CacheInterface;

/**
 * Adds a cache around a product provider, with the same interface (US-21).
 *
 * Decorator: the HTTP provider does not know it is cached. The list and each product
 * are kept CACHE_TTL seconds (5 minutes by default): products are read-only here, so
 * the lifetime is the only invalidation, and a traffic peak costs one API call per
 * key every 5 minutes instead of one per page view.
 *
 * Stale-if-error: when the API is unavailable, a copy that expired less than one day
 * ago is served and a warning logged. A catalogue that goes down loses sales; beyond
 * one day, a 503 is better than an outdated price. Only successful answers are cached.
 */
final readonly class CachedProductProvider implements ProductProviderInterface
{
    private const string LIST_KEY = 'products_all';

    private const int STALE_MAX_AGE_SECONDS = 86_400;

    public function __construct(
        private ProductProviderInterface $provider,
        private CacheInterface $cache,
        private int $ttlSeconds,
    ) {}

    /**
     * The list from the cache while fresh; from the API otherwise, or a stale copy if the API is down.
     */
    public function getAll(): array
    {
        $cached = $this->cache->get(self::LIST_KEY);
        if (self::isProductList($cached)) {
            return $cached;
        }

        try {
            $products = $this->provider->getAll();
        } catch (ProductApiUnavailableException $exception) {
            $stale = $this->cache->getStale(self::LIST_KEY, self::STALE_MAX_AGE_SECONDS);

            return self::isProductList($stale) ? $this->servedStale(self::LIST_KEY, $stale, $exception) : throw $exception;
        }

        $this->cache->set(self::LIST_KEY, $products, $this->ttlSeconds);

        return $products;
    }

    /**
     * The product from the cache while fresh; from the API otherwise, or a stale copy if the API is down.
     * A product not found is never cached nor served from an old copy.
     */
    public function getById(int $id): Product
    {
        // Not a product id: let the provider refuse it, nothing to cache.
        if ($id < 1) {
            return $this->provider->getById($id);
        }

        $key = 'product_' . $id;
        $cached = $this->cache->get($key);
        if ($cached instanceof Product) {
            return $cached;
        }

        try {
            // ProductNotFoundException goes up untouched: a removed product must not come back from an old copy.
            $product = $this->provider->getById($id);
        } catch (ProductApiUnavailableException $exception) {
            $stale = $this->cache->getStale($key, self::STALE_MAX_AGE_SECONDS);

            return $stale instanceof Product ? $this->servedStale($key, $stale, $exception) : throw $exception;
        }

        $this->cache->set($key, $product, $this->ttlSeconds);

        return $product;
    }

    /**
     * A cache entry is checked before use: a damaged file must be a miss, not a crash.
     *
     * @phpstan-assert-if-true list<Product> $value
     */
    private static function isProductList(mixed $value): bool
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return false;
        }

        return array_all($value, static fn(mixed $product): bool => $product instanceof Product);
    }

    /**
     * Logs the outage, then hands the stale copy back.
     *
     * @template T
     *
     * @param T $copy
     *
     * @return T
     */
    private function servedStale(string $key, mixed $copy, ProductApiUnavailableException $exception): mixed
    {
        error_log(\sprintf('Warning: products API unavailable, serving stale copy of "%s" (%s)', $key, $exception->getMessage()));

        return $copy;
    }
}
