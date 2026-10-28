<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'subtotal' => (string) $this->subtotal,
            'tax_total' => (string) $this->tax_total,
            'grand_total' => (string) $this->grand_total,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
