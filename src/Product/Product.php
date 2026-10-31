<?php

declare(strict_types=1);

namespace App\Product;

/**
 * A catalogue product, read-only, as the rest of the application sees it (US-20).
 *
 * Built only by a product provider: the API's JSON format never leaves the adapter.
 * FakeStore gives one image per product; without a usable one, templates show a placeholder.
 */
final readonly class Product
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public Money $price,
        public ?string $imageUrl,
    ) {}
}
