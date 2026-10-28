<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(rand(2, 3), true)),
            'code' => strtoupper(fake()->unique()->bothify('SKU-???-####')),
            // Generated as a decimal STRING so no float ever touches money.
            'price' => number_format(fake()->randomFloat(2, 199, 49999), 2, '.', ''),
            'tax_rate' => fake()->randomElement(['0.00', '5.00', '12.00', '18.00']),
            'stock_quantity' => fake()->numberBetween(0, 250),
        ];
    }

    /**
     * A product that falls inside the default low-stock threshold.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => fake()->numberBetween(0, 5),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * Fix the stock to an exact figure - used heavily by concurrency tests
     * that need "exactly one unit left".
     */
    public function withStock(int $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => $quantity,
        ]);
    }

    public function priced(string $price, string $taxRate = '0.00'): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => $price,
            'tax_rate' => $taxRate,
        ]);
    }
}
