<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Data\OrderLineInput;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates POST /api/orders.
 *
 * Security notes
 * --------------
 * - The rule set is an ALLOW LIST. Any extra key the client sends (price,
 *   tax, total, discount, is_paid...) is simply never read: validated() only
 *   returns keys that have rules, and the service layer is handed a typed DTO
 *   carrying nothing but product_id and quantity.
 * - Quantities must be positive integers, so 0, -5, 1.5 and "abc" are all
 *   rejected before any database work happens.
 * - Duplicate product ids are rejected (see withValidator) per the documented
 *   behaviour for this project.
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * Authorisation is handled by the auth:sanctum middleware on the route.
     * Any authenticated token holder may place an order.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'min:2', 'max:255'],
            'customer.email' => ['required', 'string', 'email:rfc', 'max:255'],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:'.config('inventory.max_order_items'),
            ],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => [
                'required',
                'integer',
                'min:1',
                // Confirms the product exists. Note this is a convenience
                // check for a clear 422; the authoritative existence check
                // happens again inside the locked transaction.
                Rule::exists(Product::class, 'id'),
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.config('inventory.max_line_quantity'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'An order must contain at least one item.',
            'items.min' => 'An order must contain at least one item.',
            'items.max' => 'An order may not contain more than :max line items.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.quantity.integer' => 'Quantity must be a whole number.',
            'customer.email.email' => 'A valid email address is required.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer.name' => 'customer name',
            'customer.email' => 'customer email',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
        ];
    }

    /**
     * Reject the same product appearing twice in one order.
     *
     * Chosen behaviour for this project: an explicit 422 rather than silently
     * merging the quantities. Merging would mean the server quietly changed
     * what was asked for, and a client that sends the same product twice has
     * almost certainly made a mistake worth surfacing. The order_items table
     * also carries a unique(order_id, product_id) constraint enforcing the
     * same rule at the database level.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            $seen = [];

            foreach ($items as $index => $item) {
                if (! is_array($item) || ! isset($item['product_id'])) {
                    continue;
                }

                $productId = $item['product_id'];

                if (! is_scalar($productId)) {
                    continue;
                }

                $productId = (string) $productId;

                if (isset($seen[$productId])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'This product appears more than once in the order. '
                        .'Combine the duplicate lines into a single item with the total quantity.',
                    );

                    continue;
                }

                $seen[$productId] = $index;
            }
        });
    }

    /**
     * The validated line items as typed DTOs.
     *
     * Converting here means the service layer physically cannot receive a
     * client-supplied price - the DTO has no field for one.
     *
     * @return list<OrderLineInput>
     */
    public function lineItems(): array
    {
        /** @var array<int, array{product_id: int|string, quantity: int|string}> $items */
        $items = $this->validated('items');

        return array_values(array_map(
            static fn (array $item): OrderLineInput => new OrderLineInput(
                productId: (int) $item['product_id'],
                quantity: (int) $item['quantity'],
            ),
            $items,
        ));
    }

    public function customerName(): string
    {
        return trim((string) $this->validated('customer.name'));
    }

    public function customerEmail(): string
    {
        return trim((string) $this->validated('customer.email'));
    }
}
