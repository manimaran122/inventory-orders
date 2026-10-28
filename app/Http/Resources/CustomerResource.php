<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // whenCounted / whenLoaded avoid triggering a lazy query: the
            // field only appears if the caller eager-loaded it.
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
