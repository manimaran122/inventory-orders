<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Low stock threshold
    |--------------------------------------------------------------------------
    |
    | Used by GET /api/products/low-stock when the caller does not supply a
    | `threshold` query parameter. A product is considered "low stock" when its
    | stock_quantity is less than or equal to this value.
    |
    */

    'low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Maximum low stock threshold
    |--------------------------------------------------------------------------
    |
    | Upper bound a client may request. This stops a caller passing an absurd
    | value (e.g. threshold=999999999) to dump the whole catalogue in one
    | request, which would be both a performance and a data-exposure concern.
    |
    */

    'max_low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_MAX_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Order limits
    |--------------------------------------------------------------------------
    |
    | Guard rails on order size. Without these a single request could ask for
    | thousands of line items and hold database row locks for a long time.
    |
    */

    'max_order_items' => (int) env('INVENTORY_MAX_ORDER_ITEMS', 50),

    'max_line_quantity' => (int) env('INVENTORY_MAX_LINE_QUANTITY', 1000),

    /*
    |--------------------------------------------------------------------------
    | Money handling
    |--------------------------------------------------------------------------
    |
    | All monetary values are stored as DECIMAL in the database and computed in
    | integer minor units (cents) in PHP to avoid binary floating point drift.
    | `scale` is the number of decimal places money is rounded to.
    |
    */

    'money' => [
        'scale' => 2,
        'currency' => env('INVENTORY_CURRENCY', 'INR'),
        'currency_symbol' => env('INVENTORY_CURRENCY_SYMBOL', '₹'),
    ],

];
