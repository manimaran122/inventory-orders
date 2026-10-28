<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Lower-cased to match the normalisation the application applies,
            // so factory-made customers behave like request-made ones.
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
        ];
    }

    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => mb_strtolower($email),
        ]);
    }
}
