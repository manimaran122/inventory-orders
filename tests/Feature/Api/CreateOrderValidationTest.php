<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\SendOrderConfirmation;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();
    }

    #[Test]
    public function it_rejects_an_empty_item_list(): void
    {
        $this->postJson('/api/orders', [
            'customer' => ['name' => 'No Items', 'email' => 'noitems@example.test'],
            'items' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    #[Test]
    public function it_rejects_a_missing_item_list(): void
    {
        $this->postJson('/api/orders', [
            'customer' => ['name' => 'No Items', 'email' => 'noitems@example.test'],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    #[Test]
    public function it_rejects_a_product_id_that_does_not_exist(): void
    {
        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Bad Product', 'email' => 'badproduct@example.test'],
            'items' => [['product_id' => 999999, 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('customers', 0);
    }

    #[Test]
    #[DataProvider('invalidQuantities')]
    public function it_rejects_invalid_quantities(mixed $quantity): void
    {
        $product = Product::factory()->withStock(100)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Bad Qty', 'email' => 'badqty@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-5],
            'fractional' => [1.5],
            'non numeric' => ['abc'],
            'null' => [null],
            'array' => [[1]],
            'above the per line maximum' => [1000000],
        ];
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    #[Test]
    #[DataProvider('invalidCustomers')]
    public function it_rejects_invalid_customer_details(array $customer, string $errorKey): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors($errorKey);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCustomers(): array
    {
        return [
            'missing name' => [['email' => 'a@example.test'], 'customer.name'],
            'empty name' => [['name' => '', 'email' => 'a@example.test'], 'customer.name'],
            'name too short' => [['name' => 'A', 'email' => 'a@example.test'], 'customer.name'],
            'missing email' => [['name' => 'Someone'], 'customer.email'],
            'malformed email' => [['name' => 'Someone', 'email' => 'not-an-email'], 'customer.email'],
            'email without domain' => [['name' => 'Someone', 'email' => 'someone@'], 'customer.email'],
            'name too long' => [['name' => str_repeat('a', 256), 'email' => 'a@example.test'], 'customer.name'],
        ];
    }

    #[Test]
    public function it_rejects_a_missing_customer_object(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('customer');
    }

    #[Test]
    public function it_rejects_duplicate_products_in_one_order(): void
    {
        $product = Product::factory()->withStock(100)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Dup Buyer', 'email' => 'dup@example.test'],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.1.product_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(100, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function it_rejects_more_line_items_than_the_configured_maximum(): void
    {
        $products = Product::factory()->count(3)->withStock(10)->create();

        config(['inventory.max_order_items' => 2]);

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Too Many', 'email' => 'toomany@example.test'],
            'items' => $products->map(static fn (Product $p): array => [
                'product_id' => $p->id,
                'quantity' => 1,
            ])->all(),
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    #[Test]
    public function it_does_not_dispatch_a_confirmation_job_when_validation_fails(): void
    {
        Queue::fake();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Nope', 'email' => 'not-an-email'],
            'items' => [],
        ])->assertStatus(422);

        Queue::assertNotPushed(SendOrderConfirmation::class);
    }
}
