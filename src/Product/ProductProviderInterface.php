<?php

declare(strict_types=1);

namespace App\Product;

/**
 * The single way the application reads products (US-20).
 *
 * Implemented by the HTTP adapter, by its cache decorator and by the fake provider of
 * the tests: all of them honour the same contract, exceptions included.
 */
interface ProductProviderInterface
{
    /**
     * Every product of the catalogue, in the API's order.
     *
     * @return list<Product>
     *
     * @throws ProductApiUnavailableException when the catalogue cannot be read
     */
    public function getAll(): array;

    /**
     * @throws ProductNotFoundException when no product has this id
     * @throws ProductApiUnavailableException when the product cannot be read
     */
    public function getById(int $id): Product;
}
