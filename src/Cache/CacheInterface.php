<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Key-value cache with a lifetime, which keeps expired entries as a fallback (US-21).
 *
 * Files today; Redis in production would be another implementation of this interface.
 * null means "nothing usable": null itself is never stored.
 */
interface CacheInterface
{
    /**
     * The value, only while it has not expired.
     *
     * @throws \InvalidArgumentException when the key is not a plain name
     */
    public function get(string $key): mixed;

    /**
     * The value, even expired, as long as it expired less than $maxAgeSeconds ago.
     * Used to keep the catalogue online while the products API is down.
     *
     * @throws \InvalidArgumentException when the key is not a plain name
     */
    public function getStale(string $key, int $maxAgeSeconds): mixed;

    /**
     * @throws \InvalidArgumentException when the key is not a plain name
     */
    public function set(string $key, mixed $value, int $ttlSeconds): void;
}
