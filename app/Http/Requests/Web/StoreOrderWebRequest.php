<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Data\OrderLineInput;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the browser order form.
 *
 * The rules mirror the API StoreOrderRequest because both feed the same
 * OrderService. The separate class exists because the HTML form posts a
 * slightly different shape (a flat indexed array including blank rows the user
 * has not filled in yet) and redirects back with errors rather than returning
 * JSON.
 */
class StoreOrderWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop any completely blank line rows before validating.
     *
     * The form always renders at least one row, and a user may leave a spare
     * row empty. Pruning them here means a blank row is ignored rather than
     * producing a confusing "product is required" error for a row the user
     * never intended to fill in.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);

        if (! is_array($items)) {
            return;
        }

        $items = array_values(array_filter($items, static function ($item): bool {
            if (! is_array($item)) {
                return false;
            }

            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            return ! (
                ($productId === null || $productId === '')
                && ($quantity === null || $quantity === '')
            );
        }));

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'customer_email' => ['required', 'string', 'email:rfc', 'max:255'],

            'items' => ['required', 'array', 'min:1', 'max:'.config('inventory.max_order_items')],
            'items.*.product_id' => ['required', 'integer', 'min:1', Rule::exists(Product::class, 'id')],
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
            'items.required' => 'Add at least one product to the order.',
            'items.min' => 'Add at least one product to the order.',
            'items.*.product_id.required' => 'Choose a product for every line.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.quantity.required' => 'Enter a quantity for every line.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'customer_name.required' => 'Enter the customer name.',
            'customer_email.required' => 'Enter the customer email address.',
            'customer_email.email' => 'Enter a valid email address.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'customer name',
            'customer_email' => 'customer email',
        ];
    }

    /**
     * Reject the same product on more than one line, matching the API rule and
     * the unique(order_id, product_id) database constraint.
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
                if (! is_array($item) || ! isset($item['product_id']) || ! is_scalar($item['product_id'])) {
                    continue;
                }

                $productId = (string) $item['product_id'];

                if (isset($seen[$productId])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'This product is already on the order. Change the quantity on the existing line instead.',
                    );

                    continue;
                }

                $seen[$productId] = $index;
            }
        });
    }

    /**
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
}
