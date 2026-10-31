<?php

declare(strict_types=1);

namespace App\Product;

/**
 * No product has the requested id: the visitor sees a 404 page (US-20).
 */
final class ProductNotFoundException extends \RuntimeException {}
