<?php

declare(strict_types=1);

namespace Tests\Unit\Pagination;

use App\Http\PageNotFoundException;
use App\Pagination\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function test_the_first_page_of_two(): void
    {
        $pagination = Pagination::of(20, 10, 1);

        self::assertSame(2, $pagination->pageCount);
        self::assertSame(0, $pagination->offset());
        self::assertFalse($pagination->hasPrevious());
        self::assertTrue($pagination->hasNext());
        self::assertSame([1, 2], $pagination->pages());
    }

    public function test_the_last_page(): void
    {
        $pagination = Pagination::of(20, 10, 2);

        self::assertSame(10, $pagination->offset());
        self::assertTrue($pagination->hasPrevious());
        self::assertFalse($pagination->hasNext());
    }

    public function test_an_incomplete_last_page_still_counts(): void
    {
        self::assertSame(3, Pagination::of(21, 10, 1)->pageCount);
        self::assertSame(3, Pagination::of(20, 8, 1)->pageCount);
    }

    public function test_an_empty_list_has_one_empty_page(): void
    {
        $pagination = Pagination::of(0, 10, 1);

        self::assertSame(1, $pagination->pageCount);
        self::assertFalse($pagination->hasNext());
        self::assertSame([], $pagination->slice([]));
    }

    public function test_it_keeps_the_items_of_the_current_page(): void
    {
        $items = range(1, 20);

        self::assertSame(range(1, 10), Pagination::of(20, 10, 1)->slice($items));
        self::assertSame(range(11, 20), Pagination::of(20, 10, 2)->slice($items));
        self::assertSame([17, 18, 19, 20], Pagination::of(20, 8, 3)->slice($items));
    }

    public function test_it_numbers_the_items_of_the_page(): void
    {
        // « Avis 11 à 20 sur 23 »
        self::assertSame([1, 10], [Pagination::of(23, 10, 1)->firstItemNumber(), Pagination::of(23, 10, 1)->lastItemNumber()]);
        self::assertSame([11, 20], [Pagination::of(23, 10, 2)->firstItemNumber(), Pagination::of(23, 10, 2)->lastItemNumber()]);
        self::assertSame([21, 23], [Pagination::of(23, 10, 3)->firstItemNumber(), Pagination::of(23, 10, 3)->lastItemNumber()]);
        self::assertSame([0, 0], [Pagination::of(0, 10, 1)->firstItemNumber(), Pagination::of(0, 10, 1)->lastItemNumber()]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function pagesOutOfRange(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'after the last page' => [3];
    }

    #[DataProvider('pagesOutOfRange')]
    public function test_a_page_out_of_range_does_not_exist(int $page): void
    {
        $this->expectException(PageNotFoundException::class);

        Pagination::of(20, 10, $page);
    }

    public function test_a_page_size_below_one_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Pagination::of(20, 0, 1);
    }

    public function test_a_missing_page_parameter_means_the_first_page(): void
    {
        self::assertSame(1, Pagination::pageFromQuery(null));
        self::assertSame(2, Pagination::pageFromQuery('2'));
        self::assertSame(123456789, Pagination::pageFromQuery('123456789'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPageParameters(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'text' => ['abc'];
        yield 'leading zero' => ['01'];
        yield 'decimal' => ['1.5'];
        yield 'spaces' => [' 2'];
        yield 'plus sign' => ['+2'];
        yield 'huge number' => ['99999999999999999999'];
    }

    #[DataProvider('invalidPageParameters')]
    public function test_an_invalid_page_parameter_does_not_exist(string $parameter): void
    {
        $this->expectException(PageNotFoundException::class);

        Pagination::pageFromQuery($parameter);
    }
}
