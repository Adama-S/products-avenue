<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * The current time, injected (US-21): tests move a fake clock instead of calling sleep().
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
