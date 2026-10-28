<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            // Money is emitted as a fixed-scale STRING. JSON numbers are IEEE
            // doubles, so 19.99 can arrive in a JavaScript client as
            // 19.989999999999998; a string is exact and unambiguous.
            'price' => (string) $this->price,
            'tax_rate' => (string) $this->tax_rate,
            'stock_quantity' => $this->stock_quantity,
            'is_low_stock' => $this->stock_quantity <= (int) config('inventory.low_stock_threshold'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
