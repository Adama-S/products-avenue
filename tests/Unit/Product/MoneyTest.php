<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use App\Product\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_a_decimal_price_is_converted_to_cents(): void
    {
        $money = Money::fromDecimal(109.95, 'EUR');

        self::assertSame(10995, $money->amountInCents);
        self::assertSame('EUR', $money->currency);
    }

    public function test_the_conversion_rounds_floating_point_noise(): void
    {
        // 0.1 + 0.2 is 0.30000000000000004 as a float: the amount must still be 30 cents.
        self::assertSame(30, Money::fromDecimal(0.1 + 0.2, 'EUR')->amountInCents);
        self::assertSame(1999, Money::fromDecimal(19.99, 'EUR')->amountInCents);
    }

    public function test_an_integer_price_is_accepted(): void
    {
        self::assertSame(69500, Money::fromDecimal(695, 'EUR')->amountInCents);
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function formattedAmounts(): iterable
    {
        yield 'cents' => [1290, 'EUR', "12,90\u{a0}€"];
        yield 'round amount' => [69500, 'EUR', "695,00\u{a0}€"];
        yield 'less than one euro' => [5, 'EUR', "0,05\u{a0}€"];
        yield 'zero' => [0, 'EUR', "0,00\u{a0}€"];
        yield 'thousands' => [123450, 'EUR', "1\u{202f}234,50\u{a0}€"];
        yield 'millions' => [123456789, 'EUR', "1\u{202f}234\u{202f}567,89\u{a0}€"];
        yield 'dollar' => [1290, 'USD', "12,90\u{a0}\$"];
        yield 'currency without a symbol' => [1290, 'CHF', "12,90\u{a0}CHF"];
    }

    #[DataProvider('formattedAmounts')]
    public function test_it_formats_the_amount_the_french_way(int $cents, string $currency, string $expected): void
    {
        self::assertSame($expected, Money::fromCents($cents, $currency)->format());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function decimalAmounts(): iterable
    {
        yield 'cents' => [10995, '109.95'];
        yield 'round amount' => [69500, '695.00'];
        yield 'less than one unit' => [5, '0.05'];
    }

    #[DataProvider('decimalAmounts')]
    public function test_it_gives_the_amount_as_a_decimal_string_for_structured_data(int $cents, string $expected): void
    {
        // schema.org and Open Graph expect « 109.95 »: a dot, no thousands separator, no symbol.
        self::assertSame($expected, Money::fromCents($cents, 'EUR')->decimal());
    }

    public function test_a_negative_amount_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(-1, 'EUR');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCurrencies(): iterable
    {
        yield 'lowercase' => ['eur'];
        yield 'too long' => ['EURO'];
        yield 'too short' => ['EU'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidCurrencies')]
    public function test_an_invalid_currency_code_is_refused(string $currency): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(100, $currency);
    }
}
