<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * An immutable money value expressed in integer minor units (cents).
 *
 * Why this class exists
 * --------------------
 * Money must never be held in a PHP float. Binary floating point cannot
 * represent decimal fractions such as 0.10 exactly, so arithmetic drifts:
 *
 *     0.1 + 0.2 === 0.3          // false
 *     (int) (19.99 * 100)        // 1998, not 1999
 *
 * Over many order lines that drift becomes real money. Instead every amount is
 * stored as a whole number of cents, so all addition and multiplication is
 * exact integer arithmetic. Rounding happens once, deliberately, at the single
 * point where a fractional result is unavoidable (tax).
 */
final readonly class Money
{
    private function __construct(public int $minorUnits) {}

    /**
     * Build from an integer number of cents.
     */
    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Build from a decimal string such as "19.99", as read from a DECIMAL
     * database column.
     *
     * A string (not a float) is required so no precision has been lost before
     * we are called. Numeric input is converted via string formatting rather
     * than multiplication to avoid the (int)(19.99 * 100) === 1998 trap.
     */
    public static function fromDecimal(string|int|float $amount): self
    {
        if (is_float($amount)) {
            // Format to a fixed scale first; this rounds half-up in decimal
            // space rather than inheriting binary representation error.
            $amount = number_format($amount, self::SCALE, '.', '');
        }

        $amount = (string) $amount;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Not a valid decimal amount: {$amount}");
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        // Pad or round the fractional part to exactly SCALE digits.
        if (strlen($fraction) > self::SCALE) {
            $keep = substr($fraction, 0, self::SCALE);
            $nextDigit = (int) $fraction[self::SCALE];
            $minor = (int) $whole * self::FACTOR + (int) $keep;
            if ($nextDigit >= 5) {
                $minor++;
            }
        } else {
            $fraction = str_pad($fraction, self::SCALE, '0');
            $minor = (int) $whole * self::FACTOR + (int) ($fraction === '' ? '0' : $fraction);
        }

        return new self($negative ? -$minor : $minor);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    /**
     * Multiply by a whole number (used for unit price x quantity).
     *
     * Exact: no rounding is required because both operands are integers.
     */
    public function multipliedBy(int $multiplier): self
    {
        return new self($this->minorUnits * $multiplier);
    }

    /**
     * Apply a percentage rate, rounding half-up to the nearest cent.
     *
     * This is the ONLY place a fractional result is produced, and therefore the
     * only place rounding happens. The rate arrives as a decimal string (e.g.
     * "18.50") and is scaled to integer basis points so the multiplication
     * itself stays in integer space.
     *
     * Rounding rule: half-up (0.5 cents rounds away from zero), applied per
     * order line. Documented in the README so the arithmetic is reproducible.
     */
    public function percentage(string|int|float $rate): self
    {
        // Convert "18.50" -> 1850 basis-point-ish integer at 2dp of a percent.
        $rateMinor = self::fromDecimal($rate)->minorUnits; // e.g. 18.50 -> 1850

        // amount * rate / 100, all in integers:
        //   minorUnits * rateMinor  is cents * (percent * 100)
        //   divide by 100 (percent scale) * 100 (percent->fraction) = 10000
        $numerator = $this->minorUnits * $rateMinor;
        $denominator = self::FACTOR * 100;

        return new self(self::divideRoundHalfUp($numerator, $denominator));
    }

    /**
     * Re-denominate into another currency at a fixed exchange rate.
     *
     * The rate is a decimal string such as "96.00", meaning 96 units of the new
     * currency per unit of the old. It is scaled to an integer exactly the way
     * a percentage is, so the multiplication never leaves integer space, and
     * the result is rounded half-up to the nearest minor unit - the identical
     * rule used for tax, so converted figures round consistently with the rest
     * of the system.
     */
    public function convertedBy(string|int|float $rate): self
    {
        $rateMinor = self::fromDecimal($rate)->minorUnits; // "96.00" -> 9600

        if ($rateMinor <= 0) {
            throw new InvalidArgumentException('An exchange rate must be positive.');
        }

        return new self(self::divideRoundHalfUp(
            $this->minorUnits * $rateMinor,
            self::FACTOR,
        ));
    }

    /**
     * Integer division rounding halves away from zero.
     */
    private static function divideRoundHalfUp(int $numerator, int $denominator): int
    {
        $negative = ($numerator < 0) !== ($denominator < 0);
        $numerator = abs($numerator);
        $denominator = abs($denominator);

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        // Round up when the remainder is at least half the denominator.
        if ($remainder * 2 >= $denominator) {
            $quotient++;
        }

        return $negative ? -$quotient : $quotient;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    /**
     * Render as a decimal string suitable for a DECIMAL column or JSON, e.g.
     * "1999" cents becomes "19.99".
     */
    public function toDecimalString(): string
    {
        $negative = $this->minorUnits < 0;
        $abs = abs($this->minorUnits);

        $whole = intdiv($abs, self::FACTOR);
        $fraction = $abs % self::FACTOR;

        return sprintf(
            '%s%d.%0'.self::SCALE.'d',
            $negative ? '-' : '',
            $whole,
            $fraction,
        );
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    /** Decimal places money is rounded to. */
    private const int SCALE = 2;

    /** 10 ** SCALE. */
    private const int FACTOR = 100;
}
