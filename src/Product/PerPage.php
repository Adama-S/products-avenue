<?php

declare(strict_types=1);

namespace App\Product;

/**
 * Number of products per catalogue page the visitor can choose, via ?per_page= (US-22).
 *
 * A closed list: ?per_page=100000 must cost nothing, it falls back to the default.
 * The choice lives in the URL, never in a session: the catalogue sets no cookie and
 * stays cacheable by browsers and proxies.
 */
enum PerPage: int
{
    case Ten = 10;
    case Twenty = 20;
    case Thirty = 30;

    /**
     * @return self|null null when the parameter is missing or not one of the offered sizes
     */
    public static function fromQuery(?string $parameter): ?self
    {
        if ($parameter === null || preg_match('/^\d{1,3}$/', $parameter) !== 1) {
            return null;
        }

        return self::tryFrom((int) $parameter);
    }
}
