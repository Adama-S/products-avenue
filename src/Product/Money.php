<?php

declare(strict_types=1);

namespace App\Product;

/**
 * An amount of money: integer cents and an ISO 4217 currency, never one without the other (US-20).
 *
 * Cents avoid floating-point errors: 0.1 + 0.2 is not 0.3 as a float, and a price
 * must add up to the cent. The same rule applies to part 1 (price_cents columns).
 * No arithmetic yet, as the catalogue only displays prices: the first operation added
 * (a cart total, for instance) must refuse two different currencies.
 */
final readonly class Money
{
    /** Symbols of the currencies we display; any other currency shows its ISO code. */
    private const array SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];

    /** French typography: narrow no-break space between thousands, no-break space before the symbol. */
    private const string THOUSANDS_SEPARATOR = "\u{202f}";

    private const string SYMBOL_SEPARATOR = "\u{a0}";

    /**
     * @throws \InvalidArgumentException when the amount is negative or the currency is not 3 uppercase letters
     */
    private function __construct(
        public int $amountInCents,
        public string $currency,
    ) {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException(\sprintf('A price cannot be negative, got %d cents.', $amountInCents));
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid ISO 4217 currency code "%s".', $currency));
        }
    }

    /**
     * @throws \InvalidArgumentException when the amount is negative or the currency is invalid
     */
    public static function fromCents(int $amountInCents, string $currency): self
    {
        return new self($amountInCents, $currency);
    }

    /**
     * Converts a decimal price (as an API sends it) to cents, once, when it is read.
     * Rounding absorbs the float noise: 19.99 * 100 is 1998.9999999999998.
     *
     * @throws \InvalidArgumentException when the amount is negative or the currency is invalid
     */
    public static function fromDecimal(int|float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    /**
     * French display format, computed on integers only: "1 234,50 €".
     * No intl here: a few currencies are enough, and the result stays predictable in tests.
     */
    public function format(): string
    {
        $units = number_format(intdiv($this->amountInCents, 100), 0, '', self::THOUSANDS_SEPARATOR);
        $cents = str_pad((string) ($this->amountInCents % 100), 2, '0', STR_PAD_LEFT);

        return $units . ',' . $cents . self::SYMBOL_SEPARATOR . (self::SYMBOLS[$this->currency] ?? $this->currency);
    }
}
