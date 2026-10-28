<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'reference' => Order::generateReference(),
            'customer_id' => Customer::factory(),
            // Totals default to zero; realistic figures come from either the
            // OrderService (production path) or the seeder, both of which
            // compute them from the line items rather than inventing them.
            'subtotal' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '0.00',
        ];
    }

    public function for_customer(Customer $customer): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_id' => $customer->id,
        ]);
    }
}
