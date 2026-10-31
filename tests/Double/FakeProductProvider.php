<?php

declare(strict_types=1);

namespace Tests\Double;

use App\Product\Money;
use App\Product\Product;
use App\Product\ProductApiUnavailableException;
use App\Product\ProductNotFoundException;
use App\Product\ProductProviderInterface;

/**
 * In-memory products, with an API that can go down: honours the same contract as the real provider.
 */
final class FakeProductProvider implements ProductProviderInterface
{
    public private(set) int $calls = 0;

    private bool $down = false;

    /**
     * @param list<Product> $products
     */
    public function __construct(private array $products) {}

    /**
     * @param int ...$ids
     */
    public static function withIds(int ...$ids): self
    {
        return new self(array_map(self::product(...), array_values($ids)));
    }

    public static function product(int $id): Product
    {
        return new Product($id, 'Product ' . $id, 'Description of product ' . $id, Money::fromCents(1000 + $id, 'EUR'), null);
    }

    public function goDown(): void
    {
        $this->down = true;
    }

    public function comeBack(): void
    {
        $this->down = false;
    }

    public function remove(int $id): void
    {
        $this->products = array_values(array_filter($this->products, static fn(Product $product): bool => $product->id !== $id));
    }

    public function getAll(): array
    {
        $this->call();

        return $this->products;
    }

    public function getById(int $id): Product
    {
        $this->call();
        foreach ($this->products as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        throw new ProductNotFoundException(\sprintf('No product with id %d.', $id));
    }

    private function call(): void
    {
        ++$this->calls;
        if ($this->down) {
            throw new ProductApiUnavailableException('Fake API is down.');
        }
    }
}
