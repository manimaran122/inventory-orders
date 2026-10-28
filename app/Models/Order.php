<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property string $subtotal
 * @property string $tax_total
 * @property string $grand_total
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'customer_id',
        'subtotal',
        'tax_total',
        'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Generate a human-facing order reference, e.g. "ORD-20261026-A1B2C3D4".
     *
     * Random rather than sequential so the reference does not disclose how many
     * orders the system has taken, and is not trivially guessable.
     */
    public static function generateReference(): string
    {
        return sprintf(
            'ORD-%s-%s',
            now()->format('Ymd'),
            Str::upper(Str::random(8)),
        );
    }

    public function grandTotalAsMoney(): Money
    {
        return Money::fromDecimal($this->grand_total);
    }
}
