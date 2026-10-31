<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\FileCache;
use App\Product\Money;
use App\Product\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Double\FrozenClock;
use Tests\Support\TemporaryDirectory;

final class FileCacheTest extends TestCase
{
    private const int TTL = 300;

    private TemporaryDirectory $directory;

    private FrozenClock $clock;

    private FileCache $cache;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->clock = new FrozenClock();
        $this->cache = $this->cache();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function test_a_missing_key_is_a_miss(): void
    {
        self::assertNull($this->cache->get('products_all'));
        self::assertNull($this->cache->getStale('products_all', 86400));
    }

    public function test_a_value_is_served_until_it_expires(): void
    {
        $this->cache->set('products_all', ['a', 'b'], self::TTL);
        $this->clock->advance(self::TTL - 1);

        self::assertSame(['a', 'b'], $this->cache->get('products_all'));
    }

    public function test_an_expired_value_is_a_miss(): void
    {
        $this->cache->set('products_all', ['a', 'b'], self::TTL);
        $this->clock->advance(self::TTL);

        self::assertNull($this->cache->get('products_all'));
    }

    public function test_an_expired_value_stays_available_as_a_stale_copy(): void
    {
        $this->cache->set('products_all', ['a', 'b'], self::TTL);
        $this->clock->advance(self::TTL + 3600);

        self::assertSame(['a', 'b'], $this->cache->getStale('products_all', 86400));
    }

    public function test_a_stale_copy_older_than_the_limit_is_a_miss(): void
    {
        $this->cache->set('products_all', ['a', 'b'], self::TTL);
        $this->clock->advance(self::TTL + 86400);

        self::assertNull($this->cache->getStale('products_all', 86400));
    }

    public function test_a_fresh_value_is_also_a_valid_stale_copy(): void
    {
        $this->cache->set('products_all', ['a', 'b'], self::TTL);

        self::assertSame(['a', 'b'], $this->cache->getStale('products_all', 86400));
    }

    public function test_products_survive_a_new_cache_instance(): void
    {
        $product = new Product(1, 'Backpack', 'A bag.', Money::fromCents(10995, 'EUR'), 'https://fakestoreapi.com/img/1.png');
        $this->cache->set('product_1', $product, self::TTL);

        self::assertEquals($product, $this->cache()->get('product_1'));
    }

    public function test_a_new_value_replaces_the_old_one(): void
    {
        $this->cache->set('products_all', ['old'], self::TTL);
        $this->cache->set('products_all', ['new'], self::TTL);

        self::assertSame(['new'], $this->cache->get('products_all'));
    }

    public function test_writing_leaves_no_temporary_file(): void
    {
        $this->cache->set('products_all', ['a'], self::TTL);
        $this->cache->set('product_1', ['b'], self::TTL);

        self::assertSame(['product_1.cache', 'products_all.cache'], $this->files());
    }

    public function test_a_corrupted_file_is_a_miss(): void
    {
        $this->cache->set('products_all', ['a'], self::TTL);
        file_put_contents($this->directory->path . '/products_all.cache', 'not a cache entry');

        self::assertNull($this->cache->get('products_all'));
        self::assertNull($this->cache->getStale('products_all', 86400));
    }

    public function test_an_object_of_an_unexpected_class_is_never_rebuilt(): void
    {
        // A tampered file must not instantiate arbitrary classes (PHP object injection).
        $this->cache->set('products_all', new \ArrayObject(['a']), self::TTL);

        self::assertNotInstanceOf(\ArrayObject::class, $this->cache->get('products_all'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'path traversal' => ['../products'];
        yield 'slash' => ['products/all'];
        yield 'empty' => [''];
        yield 'uppercase' => ['Products'];
    }

    #[DataProvider('invalidKeys')]
    public function test_a_key_that_is_not_a_plain_name_is_refused(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->cache->get($key);
    }

    private function cache(): FileCache
    {
        return new FileCache($this->directory->path, $this->clock, [Product::class, Money::class]);
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = array_values(array_diff(scandir($this->directory->path) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }
}
