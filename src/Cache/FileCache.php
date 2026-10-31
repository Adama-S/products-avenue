<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Cache stored as one file per key in var/cache (US-21).
 *
 * Enough for a single server. Entries keep their expiry date and stay on disk once
 * expired, so the catalogue can fall back on them during an API outage.
 * A cache is an optimisation: a write that fails is logged and the page still works.
 */
final readonly class FileCache implements CacheInterface
{
    /**
     * @param list<class-string> $allowedClasses the only classes a cache file may rebuild
     */
    public function __construct(
        private string $directory,
        private ClockInterface $clock,
        private array $allowedClasses,
    ) {}

    public function get(string $key): mixed
    {
        $entry = $this->read($key);

        return $entry !== null && $this->now() < $entry['expiresAt'] ? $entry['value'] : null;
    }

    public function getStale(string $key, int $maxAgeSeconds): mixed
    {
        $entry = $this->read($key);

        return $entry !== null && $this->now() < $entry['expiresAt'] + $maxAgeSeconds ? $entry['value'] : null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $path = $this->path($key);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true)) {
            error_log('Cache write failed: cannot create ' . $this->directory);

            return;
        }

        // Atomic write: a visitor never reads a half-written file, the rename swaps it whole.
        $temporary = tempnam($this->directory, $key . '.tmp');
        $contents = serialize(['expiresAt' => $this->now() + $ttlSeconds, 'value' => $value]);
        if ($temporary === false || file_put_contents($temporary, $contents) === false || !rename($temporary, $path)) {
            error_log('Cache write failed: ' . $path);
            if (\is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * @return array{expiresAt: int, value: mixed}|null null when missing, damaged or unreadable
     */
    private function read(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        // allowed_classes: a tampered file cannot instantiate any other class (object injection).
        // @: a damaged file is an expected case, handled below as a miss, not a warning.
        $entry = $contents === false ? false : @unserialize($contents, ['allowed_classes' => $this->allowedClasses]);
        if (!\is_array($entry) || !\is_int($entry['expiresAt'] ?? null) || !\array_key_exists('value', $entry)) {
            return null;
        }

        return ['expiresAt' => $entry['expiresAt'], 'value' => $entry['value']];
    }

    /**
     * @throws \InvalidArgumentException when the key could escape the cache directory
     */
    private function path(string $key): string
    {
        if (preg_match('/^[a-z0-9_]+$/', $key) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid cache key "%s".', $key));
        }

        return $this->directory . '/' . $key . '.cache';
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
