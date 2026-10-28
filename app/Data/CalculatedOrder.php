<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Money;

/**
 * The computed money for an entire order.
 */
final readonly class CalculatedOrder
{
    /**
     * @param  list<CalculatedLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $subtotal,
        public Money $taxTotal,
        public Money $grandTotal,
    ) {}
}
