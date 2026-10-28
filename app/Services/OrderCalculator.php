<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CalculatedLine;
use App\Data\CalculatedOrder;
use App\Data\OrderLineInput;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Turns validated line inputs plus product records into exact money figures.
 *
 * This class is intentionally free of database writes and HTTP concerns so the
 * arithmetic can be unit tested in isolation.
 *
 * Calculation rules (also documented in the README):
 *
 *   line_subtotal = unit_price * quantity                (exact, integer maths)
 *   line_tax      = round(line_subtotal * tax_rate / 100) (half-up, per line)
 *   line_total    = line_subtotal + line_tax
 *   subtotal      = sum(line_subtotal)
 *   tax_total     = sum(line_tax)
 *   grand_total   = subtotal + tax_total
 *
 * Tax is rounded once per line and the rounded values are then summed. Summing
 * rounded lines (rather than rounding the summed tax) keeps every stored line
 * internally consistent: the order total always equals the sum of its printed
 * line totals, which is what an auditor or a customer reading an invoice
 * expects.
 */
final class OrderCalculator
{
    /**
     * @param  list<OrderLineInput>  $lines
     * @param  Collection<int, Product>  $productsById  keyed by product id
     */
    public function calculate(array $lines, Collection $productsById): CalculatedOrder
    {
        $calculated = [];
        $subtotal = Money::zero();
        $taxTotal = Money::zero();

        foreach ($lines as $line) {
            $product = $productsById->get($line->productId);

            if (! $product instanceof Product) {
                // Guarded by validation long before this point; this is a
                // defensive assertion rather than an expected branch.
                throw new \LogicException(
                    "Product {$line->productId} was not loaded for calculation."
                );
            }

            // Price and tax rate come from the PRODUCT ROW, never the request.
            $unitPrice = $product->priceAsMoney();
            $taxRate = (string) $product->tax_rate;

            $lineSubtotal = $unitPrice->multipliedBy($line->quantity);
            $lineTax = $lineSubtotal->percentage($taxRate);
            $lineTotal = $lineSubtotal->plus($lineTax);

            $calculated[] = new CalculatedLine(
                productId: $product->id,
                quantity: $line->quantity,
                unitPrice: $unitPrice,
                taxRate: $taxRate,
                lineSubtotal: $lineSubtotal,
                lineTax: $lineTax,
                lineTotal: $lineTotal,
            );

            $subtotal = $subtotal->plus($lineSubtotal);
            $taxTotal = $taxTotal->plus($lineTax);
        }

        return new CalculatedOrder(
            lines: $calculated,
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            grandTotal: $subtotal->plus($taxTotal),
        );
    }
}
