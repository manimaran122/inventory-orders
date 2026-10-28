<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an order cannot be fulfilled from available stock.
 *
 * Carries per-product detail so the API can tell the caller exactly which line
 * failed and what is actually available - useful for a UI, and safe to expose
 * because stock levels are already visible through the product endpoints.
 */
class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{product_id:int, product_name:string, requested:int, available:int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        $names = implode(', ', array_map(
            static fn (array $s): string => sprintf(
                '%s (requested %d, available %d)',
                $s['product_name'],
                $s['requested'],
                $s['available'],
            ),
            $shortages,
        ));

        parent::__construct("Insufficient stock for: {$names}");
    }
}
