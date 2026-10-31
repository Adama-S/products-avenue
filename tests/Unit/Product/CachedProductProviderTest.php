<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use App\Cache\FileCache;
use App\Product\CachedProductProvider;
use App\Product\Money;
use App\Product\Product;
use App\Product\ProductApiUnavailableException;
use App\Product\ProductNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Double\FakeProductProvider;
use Tests\Double\FrozenClock;
use Tests\Support\TemporaryDirectory;

final class CachedProductProviderTest extends TestCase
{
    private const int TTL = 300;

    private const int ONE_DAY = 86400;

    private TemporaryDirectory $directory;

    private FrozenClock $clock;

    private FakeProductProvider $api;

    private CachedProductProvider $provider;

    private string $logFile;

    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->clock = new FrozenClock();
        $this->api = FakeProductProvider::withIds(1, 2, 3);
        $cache = new FileCache($this->directory->path, $this->clock, [Product::class, Money::class]);
        $this->provider = new CachedProductProvider($this->api, $cache, self::TTL);

        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        unlink($this->logFile);
        $this->directory->remove();
    }

    public function test_the_list_is_read_from_the_api_once_while_fresh(): void
    {
        $first = $this->provider->getAll();
        $this->clock->advance(self::TTL - 1);
        $second = $this->provider->getAll();

        self::assertCount(3, $first);
        self::assertEquals($first, $second);
        self::assertSame(1, $this->api->calls);
    }

    public function test_a_product_is_read_from_the_api_once_while_fresh(): void
    {
        $this->provider->getById(2);
        $product = $this->provider->getById(2);

        self::assertSame(2, $product->id);
        self::assertSame(1, $this->api->calls);
    }

    public function test_the_api_is_called_again_once_the_copy_has_expired(): void
    {
        $this->provider->getAll();
        $this->clock->advance(self::TTL);
        $this->provider->getAll();

        self::assertSame(2, $this->api->calls);
    }

    public function test_an_expired_list_is_served_while_the_api_is_down(): void
    {
        $this->provider->getAll();
        $this->clock->advance(self::TTL + 3600);
        $this->api->goDown();

        self::assertCount(3, $this->provider->getAll());
        self::assertStringContainsString('serving stale copy of "products_all"', $this->logs());
    }

    public function test_an_expired_product_is_served_while_the_api_is_down(): void
    {
        $this->provider->getById(2);
        $this->clock->advance(self::TTL + 3600);
        $this->api->goDown();

        self::assertSame(2, $this->provider->getById(2)->id);
        self::assertStringContainsString('serving stale copy of "product_2"', $this->logs());
    }

    public function test_a_copy_older_than_one_day_is_not_served(): void
    {
        $this->provider->getAll();
        $this->clock->advance(self::TTL + self::ONE_DAY);
        $this->api->goDown();

        $this->expectException(ProductApiUnavailableException::class);

        $this->provider->getAll();
    }

    public function test_without_any_copy_the_api_failure_goes_up(): void
    {
        $this->api->goDown();

        $this->expectException(ProductApiUnavailableException::class);

        $this->provider->getById(2);
    }

    public function test_a_product_removed_from_the_api_is_never_served_from_an_expired_copy(): void
    {
        $this->provider->getById(2);
        $this->clock->advance(self::TTL);
        $this->api->remove(2);

        $this->expectException(ProductNotFoundException::class);

        $this->provider->getById(2);
    }

    public function test_an_api_failure_is_never_cached(): void
    {
        $this->api->goDown();
        try {
            $this->provider->getAll();
            self::fail('The API is down.');
        } catch (ProductApiUnavailableException) {
        }
        $this->api->comeBack();

        self::assertCount(3, $this->provider->getAll());
        self::assertSame(2, $this->api->calls);
    }

    public function test_an_unknown_product_is_never_cached(): void
    {
        foreach ([1, 2] as $attempt) {
            try {
                $this->provider->getById(99);
                self::fail('Product 99 does not exist.');
            } catch (ProductNotFoundException) {
            }
        }

        self::assertSame(2, $this->api->calls);
    }

    public function test_a_non_positive_id_is_not_found(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->provider->getById(-1);
    }

    private function logs(): string
    {
        return (string) file_get_contents($this->logFile);
    }
}
