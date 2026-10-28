<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            // Only present when the relationship was eager-loaded, which keeps
            // this resource free of N+1 surprises.
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            // The historic snapshot: what was charged at the time of ordering,
            // which may differ from the current product price.
            'unit_price' => (string) $this->unit_price,
            'tax_rate' => (string) $this->tax_rate,
            'line_subtotal' => (string) $this->line_subtotal,
            'line_tax' => (string) $this->line_tax,
            'line_total' => (string) $this->line_total,
        ];
    }
}
