<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $price
 * @property string $tax_rate
 * @property int $stock_quantity
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Mass-assignment allow list.
     *
     * An explicit $fillable (rather than $guarded = []) means a request body
     * can never set a column we did not intend - the core defence against
     * mass-assignment vulnerabilities.
     */
    protected $fillable = [
        'name',
        'code',
        'price',
        'tax_rate',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            // Cast money to a fixed-scale decimal STRING, not a float, so
            // precision survives the trip out of the database.
            'price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'stock_quantity' => 'integer',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The unit price as an exact money value.
     */
    public function priceAsMoney(): Money
    {
        return Money::fromDecimal($this->price);
    }

    /**
     * Products at or below the given stock threshold.
     *
     * Note "<=": a product sitting exactly at the threshold counts as low
     * stock, which is the behaviour documented in the README and covered by a
     * dedicated test.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeLowStock(Builder $query, int $threshold): void
    {
        // Parameter binding, never string concatenation, so a hostile
        // threshold value cannot alter the SQL.
        $query->where('stock_quantity', '<=', $threshold);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $term = trim($term);

        $query->where(function (Builder $q) use ($term): void {
            // Escape LIKE wildcards so a user searching for "%" does not match
            // every row.
            $escaped = addcslashes($term, '%_'.chr(92));
            $q->where('name', 'like', "%{$escaped}%")
                ->orWhere('code', 'like', "%{$escaped}%");
        });
    }
}
