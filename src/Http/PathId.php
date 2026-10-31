<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Reads an id from a URL segment (/products/{id}, /comments/{id}) (US-23, US-31).
 *
 * Only a plain positive integer is accepted, checked before the id reaches the API URL
 * or a query: '01', '1.5', '-1' or 20 digits are not ids, and each resource has one URL.
 */
final readonly class PathId
{
    /**
     * @return int|null null when the segment is not a valid id
     */
    public static function parse(string $segment): ?int
    {
        // Nine digits at most: no integer overflow, and far beyond any real id.
        return preg_match('/^[1-9]\d{0,8}$/', $segment) === 1 ? (int) $segment : null;
    }
}
