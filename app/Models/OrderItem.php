<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line of an order.
 *
 * unit_price and tax_rate are deliberately duplicated from the product rather
 * than read through the relationship: they are a historic snapshot of what the
 * customer was actually charged. Repricing a product must not retroactively
 * change past orders.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_subtotal',
        'line_tax',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
