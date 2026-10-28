<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Money;

/**
 * One fully priced order line, computed server-side from the product record.
 */
final readonly class CalculatedLine
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public Money $unitPrice,
        public string $taxRate,
        public Money $lineSubtotal,
        public Money $lineTax,
        public Money $lineTotal,
    ) {}

    /**
     * Shape used when persisting to the order_items table.
     *
     * @return array<string, string|int>
     */
    public function toDatabaseRow(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toDecimalString(),
            'tax_rate' => $this->taxRate,
            'line_subtotal' => $this->lineSubtotal->toDecimalString(),
            'line_tax' => $this->lineTax->toDecimalString(),
            'line_total' => $this->lineTotal->toDecimalString(),
        ];
    }
}
