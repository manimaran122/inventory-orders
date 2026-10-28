<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A validated, normalised request for one order line.
 *
 * Note what is NOT here: price, tax rate, line totals. Those are deliberately
 * excluded so that no code path can accidentally carry a client-supplied price
 * into the calculation. The server reads pricing from the products table only.
 */
final readonly class OrderLineInput
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}
