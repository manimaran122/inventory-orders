<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\OrderLineInput;
use App\Models\Product;
use App\Services\OrderCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The calculator touches no database, so it is a pure unit test.
 *
 * Products are built with `new Product()` and never saved, which keeps the
 * arithmetic under test and nothing else.
 */
class OrderCalculatorTest extends TestCase
{
    private function product(int $id, string $price, string $taxRate): Product
    {
        $product = new Product([
            'name' => "Product {$id}",
            'code' => "SKU-{$id}",
            'price' => $price,
            'tax_rate' => $taxRate,
            'stock_quantity' => 100,
        ]);

        $product->id = $id;

        return $product;
    }

    #[Test]
    public function it_calculates_a_single_line(): void
    {
        $calculator = new OrderCalculator;

        $result = $calculator->calculate(
            [new OrderLineInput(productId: 1, quantity: 2)],
            new Collection([1 => $this->product(1, '24.99', '18.00')]),
        );

        // 24.99 x 2 = 49.98 subtotal; 18% of 49.98 = 8.9964 -> 9.00
        $this->assertSame('49.98', $result->subtotal->toDecimalString());
        $this->assertSame('9.00', $result->taxTotal->toDecimalString());
        $this->assertSame('58.98', $result->grandTotal->toDecimalString());

        $line = $result->lines[0];
        $this->assertSame('24.99', $line->unitPrice->toDecimalString());
        $this->assertSame('18.00', $line->taxRate);
        $this->assertSame('49.98', $line->lineSubtotal->toDecimalString());
        $this->assertSame('9.00', $line->lineTax->toDecimalString());
        $this->assertSame('58.98', $line->lineTotal->toDecimalString());
    }

    #[Test]
    public function it_sums_multiple_lines_with_different_tax_rates(): void
    {
        $calculator = new OrderCalculator;

        $result = $calculator->calculate(
            [
                new OrderLineInput(productId: 1, quantity: 2),  // 24.99 @ 18%
                new OrderLineInput(productId: 2, quantity: 3),  // 7.49  @ 5%
                new OrderLineInput(productId: 3, quantity: 1),  // 15.00 @ 0%
            ],
            new Collection([
                1 => $this->product(1, '24.99', '18.00'),
                2 => $this->product(2, '7.49', '5.00'),
                3 => $this->product(3, '15.00', '0.00'),
            ]),
        );

        // Line 1: 49.98 sub, 9.00 tax
        // Line 2: 22.47 sub, 1.12 tax (1.1235 -> 1.12)
        // Line 3: 15.00 sub, 0.00 tax
        $this->assertSame('87.45', $result->subtotal->toDecimalString());
        $this->assertSame('10.12', $result->taxTotal->toDecimalString());
        $this->assertSame('97.57', $result->grandTotal->toDecimalString());
    }

    #[Test]
    public function the_grand_total_always_equals_the_sum_of_the_line_totals(): void
    {
        $calculator = new OrderCalculator;

        $result = $calculator->calculate(
            [
                new OrderLineInput(productId: 1, quantity: 7),
                new OrderLineInput(productId: 2, quantity: 13),
                new OrderLineInput(productId: 3, quantity: 3),
            ],
            new Collection([
                1 => $this->product(1, '0.99', '18.00'),
                2 => $this->product(2, '3.33', '12.00'),
                3 => $this->product(3, '19.95', '5.00'),
            ]),
        );

        $sumOfLineTotals = array_reduce(
            $result->lines,
            static fn (int $carry, $line): int => $carry + $line->lineTotal->minorUnits,
            0,
        );

        // This is the invariant that makes an invoice internally consistent:
        // the printed total matches the printed lines, to the cent.
        $this->assertSame($result->grandTotal->minorUnits, $sumOfLineTotals);
    }

    #[Test]
    public function it_handles_a_zero_tax_product(): void
    {
        $calculator = new OrderCalculator;

        $result = $calculator->calculate(
            [new OrderLineInput(productId: 1, quantity: 4)],
            new Collection([1 => $this->product(1, '15.00', '0.00')]),
        );

        $this->assertSame('60.00', $result->subtotal->toDecimalString());
        $this->assertSame('0.00', $result->taxTotal->toDecimalString());
        $this->assertSame('60.00', $result->grandTotal->toDecimalString());
    }

    #[Test]
    public function it_reads_price_from_the_product_not_the_request(): void
    {
        $calculator = new OrderCalculator;

        // The DTO has no price field at all, so the only possible source of
        // price is the product record. This test documents that guarantee.
        $result = $calculator->calculate(
            [new OrderLineInput(productId: 1, quantity: 1)],
            new Collection([1 => $this->product(1, '500.00', '0.00')]),
        );

        $this->assertSame('500.00', $result->grandTotal->toDecimalString());
    }
}
