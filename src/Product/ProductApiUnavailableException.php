<?php

declare(strict_types=1);

namespace App\Product;

/**
 * The products API cannot be used right now (network, timeout, server error, unexpected data):
 * the visitor sees a 503 page, the cause is in the logs (US-20).
 */
final class ProductApiUnavailableException extends \RuntimeException {}
