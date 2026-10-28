<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\SendOrderConfirmation;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();
    }

    #[Test]
    public function it_rejects_an_order_that_exceeds_available_stock(): void
    {
        $product = Product::factory()->withStock(2)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Greedy', 'email' => 'greedy@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])
            // 409 Conflict, not 422: the payload is valid, but the current
            // state of the world cannot satisfy it.
            ->assertStatus(409)
            ->assertJsonPath('errors.stock.0.product_id', $product->id)
            ->assertJsonPath('errors.stock.0.requested', 5)
            ->assertJsonPath('errors.stock.0.available', 2);

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function it_allows_an_order_for_exactly_the_remaining_stock(): void
    {
        $product = Product::factory()->withStock(3)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Exact', 'email' => 'exact@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function it_rejects_an_order_for_an_out_of_stock_product(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Zero Stock', 'email' => 'zerostock@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function a_failed_order_leaves_no_partial_database_changes(): void
    {
        $healthy = Product::factory()->withStock(50)->create();
        $scarce = Product::factory()->withStock(1)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Partial', 'email' => 'partial@example.test'],
            'items' => [
                // This line is satisfiable...
                ['product_id' => $healthy->id, 'quantity' => 2],
                // ...but this one is not, so the WHOLE order must roll back.
                ['product_id' => $scarce->id, 'quantity' => 99],
            ],
        ])->assertStatus(409);

        // No order, no line items, and - importantly - no customer row, even
        // though the customer is created before the stock check runs.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('customers', 0);

        // The satisfiable line must not have been deducted.
        $this->assertSame(50, $healthy->fresh()->stock_quantity);
        $this->assertSame(1, $scarce->fresh()->stock_quantity);
    }

    #[Test]
    public function it_does_not_dispatch_a_confirmation_job_when_stock_is_insufficient(): void
    {
        Queue::fake();

        $product = Product::factory()->withStock(1)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'No Job', 'email' => 'nojob@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertStatus(409);

        Queue::assertNotPushed(SendOrderConfirmation::class);
    }

    #[Test]
    public function stock_can_never_become_negative_through_sequential_orders(): void
    {
        $product = Product::factory()->withStock(5)->create();

        // Five single-unit orders should succeed, the sixth must fail.
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/orders', [
                'customer' => ['name' => "Buyer {$i}", 'email' => "buyer{$i}@example.test"],
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertCreated();
        }

        $this->assertSame(0, $product->fresh()->stock_quantity);

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Buyer 6', 'email' => 'buyer6@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(409);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 5);
    }
}
