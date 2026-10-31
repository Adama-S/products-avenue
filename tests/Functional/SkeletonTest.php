<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Placeholder that keeps the Functional suite wired until the router exists.
 * Replaced by real functional tests (router called with a simulated request) in US-22.
 */
final class SkeletonTest extends TestCase
{
    public function test_the_front_controller_exists(): void
    {
        self::assertFileExists(\dirname(__DIR__, 2) . '/public/index.php');
    }
}
