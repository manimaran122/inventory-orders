<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The money primitive underpins every financial figure in the system, so it is
 * tested in isolation and includes the classic floating point traps.
 */
class MoneyTest extends TestCase
{
    #[Test]
    public function it_parses_a_decimal_string_without_precision_loss(): void
    {
        $this->assertSame(1999, Money::fromDecimal('19.99')->minorUnits);
        $this->assertSame(0, Money::fromDecimal('0.00')->minorUnits);
        $this->assertSame(100, Money::fromDecimal('1')->minorUnits);
    }

    #[Test]
    public function it_avoids_the_float_multiplication_trap(): void
    {
        // (int) (19.99 * 100) is 1998 in PHP because 19.99 cannot be
        // represented exactly in binary. Money must not inherit that bug.
        $this->assertSame(1999, Money::fromDecimal(19.99)->minorUnits);
    }

    #[Test]
    public function adding_tenths_does_not_drift(): void
    {
        // 0.1 + 0.2 !== 0.3 in float arithmetic.
        $sum = Money::fromDecimal('0.10')->plus(Money::fromDecimal('0.20'));

        $this->assertSame('0.30', $sum->toDecimalString());
    }

    #[Test]
    public function multiplying_by_a_quantity_is_exact(): void
    {
        $this->assertSame(
            '59.97',
            Money::fromDecimal('19.99')->multipliedBy(3)->toDecimalString(),
        );
    }

    #[Test]
    public function repeated_addition_of_many_small_amounts_stays_exact(): void
    {
        $total = Money::zero();

        for ($i = 0; $i < 1000; $i++) {
            $total = $total->plus(Money::fromDecimal('0.01'));
        }

        $this->assertSame('10.00', $total->toDecimalString());
    }

    #[Test]
    #[DataProvider('percentageCases')]
    public function it_applies_percentages_with_half_up_rounding(
        string $amount,
        string $rate,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            Money::fromDecimal($amount)->percentage($rate)->toDecimalString(),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function percentageCases(): array
    {
        return [
            'zero rate' => ['100.00', '0.00', '0.00'],
            'whole result' => ['100.00', '18.00', '18.00'],
            'exact half rounds up' => ['10.00', '2.50', '0.25'],
            'half a cent rounds up' => ['0.10', '5.00', '0.01'],
            'rounds down below half' => ['0.10', '4.00', '0.00'],
            'fractional rate' => ['59.97', '18.50', '11.09'],
            'large amount' => ['9999.99', '18.00', '1800.00'],
            'zero amount' => ['0.00', '18.00', '0.00'],
        ];
    }

    #[Test]
    public function it_rounds_input_with_more_precision_than_two_decimals(): void
    {
        $this->assertSame('1.01', Money::fromDecimal('1.005')->toDecimalString());
        $this->assertSame('1.00', Money::fromDecimal('1.004')->toDecimalString());
    }

    #[Test]
    public function it_formats_small_amounts_with_a_leading_zero(): void
    {
        $this->assertSame('0.05', Money::fromMinorUnits(5)->toDecimalString());
        $this->assertSame('0.00', Money::zero()->toDecimalString());
    }

    #[Test]
    public function it_rejects_values_that_are_not_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('not-a-number');
    }

    #[Test]
    public function it_rejects_injection_style_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('1.00; DROP TABLE products');
    }
}
