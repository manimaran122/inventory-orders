<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a product referenced by an order disappeared between validation
 * and the locked read inside the transaction (for example, deleted by another
 * process a few milliseconds earlier).
 */
class ProductUnavailableException extends RuntimeException
{
    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public readonly array $productIds)
    {
        $ids = implode(', ', $productIds);

        parent::__construct("Product(s) no longer available: {$ids}");
    }
}
