<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_it_reads_the_environment(): void
    {
        $config = Config::fromArray([
            'APP_URL' => 'https://products-avenue.example/',
            'PRODUCTS_API_URL' => 'https://fakestoreapi.com',
            'PRODUCTS_API_CURRENCY' => 'USD',
            'PRODUCTS_PER_PAGE' => '8',
            'CACHE_TTL' => '5',
        ]);

        self::assertSame('https://products-avenue.example', $config->appUrl);
        self::assertSame('https://fakestoreapi.com', $config->productsApiUrl);
        self::assertSame('USD', $config->currency);
        self::assertSame(8, $config->productsPerPage);
        self::assertSame(5, $config->cacheTtl);
    }

    public function test_missing_values_take_the_documented_defaults(): void
    {
        $config = Config::fromArray([]);

        self::assertSame('http://localhost:8050', $config->appUrl);
        self::assertSame('https://fakestoreapi.com', $config->productsApiUrl);
        self::assertSame('EUR', $config->currency);
        self::assertSame(10, $config->productsPerPage);
        self::assertSame(300, $config->cacheTtl);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidEnvironments(): iterable
    {
        yield 'products per page as text' => [['PRODUCTS_PER_PAGE' => 'ten']];
        yield 'zero products per page' => [['PRODUCTS_PER_PAGE' => '0']];
        yield 'negative cache lifetime' => [['CACHE_TTL' => '-1']];
        yield 'lowercase currency' => [['PRODUCTS_API_CURRENCY' => 'eur']];
    }

    /**
     * @param array<string, string> $environment
     */
    #[DataProvider('invalidEnvironments')]
    public function test_an_invalid_value_is_refused_at_startup(array $environment): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Config::fromArray($environment);
    }
}
