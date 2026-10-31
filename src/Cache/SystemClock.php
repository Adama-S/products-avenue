<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * The real time, in UTC like every date of the application (US-21).
 */
final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
