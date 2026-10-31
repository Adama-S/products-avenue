<?php

declare(strict_types=1);

namespace Tests\Double;

use App\Cache\ClockInterface;

/**
 * A clock that only moves when the test says so: expiry is tested without sleep().
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $now = new \DateTimeImmutable('2026-10-31 12:00:00', new \DateTimeZone('UTC'))) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(\sprintf('+%d seconds', $seconds));
    }
}
