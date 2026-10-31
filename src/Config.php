<?php

declare(strict_types=1);

namespace App;

use App\Product\Money;

/**
 * Application settings, read once from the environment (US-22).
 *
 * Defaults match .env.example. An invalid value stops the application at startup,
 * with a clear message in the logs, rather than producing wrong pages.
 */
final readonly class Config
{
    private function __construct(
        public string $appUrl,
        public string $productsApiUrl,
        public string $currency,
        public int $productsPerPage,
        public int $cacheTtl,
    ) {}

    /**
     * @throws \InvalidArgumentException when a value is invalid
     */
    public static function fromEnvironment(): self
    {
        return self::fromArray(getenv());
    }

    /**
     * @param array<string, string> $environment
     *
     * @throws \InvalidArgumentException when a value is invalid
     */
    public static function fromArray(array $environment): self
    {
        $currency = $environment['PRODUCTS_API_CURRENCY'] ?? 'EUR';
        // Validated here, so a wrong currency fails at startup and not on the first product.
        Money::fromCents(0, $currency);

        return new self(
            rtrim($environment['APP_URL'] ?? 'http://localhost:8050', '/'),
            $environment['PRODUCTS_API_URL'] ?? 'https://fakestoreapi.com',
            $currency,
            self::integer($environment, 'PRODUCTS_PER_PAGE', 10, 1),
            self::integer($environment, 'CACHE_TTL', 300, 0),
        );
    }

    /**
     * @param array<string, string> $environment
     */
    private static function integer(array $environment, string $name, int $default, int $minimum): int
    {
        $value = $environment[$name] ?? (string) $default;
        if (preg_match('/^\d{1,9}$/', $value) !== 1 || (int) $value < $minimum) {
            throw new \InvalidArgumentException(\sprintf('%s must be an integer of at least %d, got "%s".', $name, $minimum, $value));
        }

        return (int) $value;
    }
}
