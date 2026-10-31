<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utcDates(): iterable
    {
        // Stored in UTC, shown in Paris time: +1 h in winter, +2 h in summer.
        yield 'winter, the day changes' => ['2026-10-30 23:26:00', '31 octobre 2026 à 00:26'];
        yield 'summer' => ['2026-07-01 08:05:00', '1 juillet 2026 à 10:05'];
        yield 'last minute of the year' => ['2026-12-31 22:59:00', '31 décembre 2026 à 23:59'];
    }

    #[DataProvider('utcDates')]
    public function test_a_utc_date_is_shown_in_french_in_paris_time(string $utc, string $expected): void
    {
        self::assertSame($expected, french_datetime(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))));
    }
}
