<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = number_format(fake()->randomFloat(2, 4.99, 499.99), 2, '.', '');
        $taxRate = fake()->randomElement(['0.00', '5.00', '12.00', '18.00']);

        // Keep the factory's own maths consistent with the production
        // calculator so seeded data is never internally contradictory.
        $lineSubtotal = Money::fromDecimal($unitPrice)->multipliedBy($quantity);
        $lineTax = $lineSubtotal->percentage($taxRate);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'line_subtotal' => $lineSubtotal->toDecimalString(),
            'line_tax' => $lineTax->toDecimalString(),
            'line_total' => $lineSubtotal->plus($lineTax)->toDecimalString(),
        ];
    }
}
