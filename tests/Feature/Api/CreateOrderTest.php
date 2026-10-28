<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();
    }

    #[Test]
    public function it_creates_an_order_and_a_new_customer(): void
    {
        $product = Product::factory()->priced('24.99', '18.00')->withStock(10)->create();

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Test Customer', 'email' => 'new.buyer@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer.email', 'new.buyer@example.test')
            ->assertJsonPath('data.subtotal', '49.98')
            ->assertJsonPath('data.tax_total', '9.00')
            ->assertJsonPath('data.grand_total', '58.98')
            ->assertJsonStructure([
                'data' => [
                    'id', 'reference', 'subtotal', 'tax_total', 'grand_total',
                    'customer' => ['id', 'name', 'email'],
                    'items' => [['id', 'product_id', 'quantity', 'unit_price',
                        'tax_rate', 'line_subtotal', 'line_tax', 'line_total',
                        'product' => ['id', 'name', 'code']]],
                ],
            ]);

        $this->assertDatabaseHas('customers', ['email' => 'new.buyer@example.test']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    #[Test]
    public function it_reuses_an_existing_customer_matched_by_email(): void
    {
        $existing = Customer::factory()->withEmail('repeat.buyer@example.test')->create([
            'name' => 'Original Name',
        ]);
        $product = Product::factory()->withStock(10)->create();

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Different Name', 'email' => 'repeat.buyer@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer.id', $existing->id)
            // The stored name is deliberately NOT overwritten by an order
            // request: placing an order is not an identity-change request.
            ->assertJsonPath('data.customer.name', 'Original Name');

        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function it_matches_an_existing_customer_regardless_of_email_case(): void
    {
        $existing = Customer::factory()->withEmail('mixed.case@example.test')->create();
        $product = Product::factory()->withStock(10)->create();

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Someone', 'email' => 'Mixed.Case@EXAMPLE.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.customer.id', $existing->id);
        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function it_creates_an_order_with_multiple_products_and_correct_totals(): void
    {
        $a = Product::factory()->priced('24.99', '18.00')->withStock(10)->create();
        $b = Product::factory()->priced('7.49', '5.00')->withStock(10)->create();
        $c = Product::factory()->priced('15.00', '0.00')->withStock(10)->create();

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Multi Buyer', 'email' => 'multi@example.test'],
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2],
                ['product_id' => $b->id, 'quantity' => 3],
                ['product_id' => $c->id, 'quantity' => 1],
            ],
        ]);

        // 49.98 + 22.47 + 15.00 = 87.45 subtotal
        // 9.00  + 1.12  + 0.00  = 10.12 tax
        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '87.45')
            ->assertJsonPath('data.tax_total', '10.12')
            ->assertJsonPath('data.grand_total', '97.57')
            ->assertJsonCount(3, 'data.items');
    }

    #[Test]
    public function the_order_total_equals_the_sum_of_its_stored_line_totals(): void
    {
        $a = Product::factory()->priced('0.99', '18.00')->withStock(50)->create();
        $b = Product::factory()->priced('3.33', '12.00')->withStock(50)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Reconcile', 'email' => 'reconcile@example.test'],
            'items' => [
                ['product_id' => $a->id, 'quantity' => 7],
                ['product_id' => $b->id, 'quantity' => 13],
            ],
        ])->assertCreated();

        $order = Order::query()->with('items')->firstOrFail();

        $sumOfLines = $order->items->reduce(
            static fn (int $carry, OrderItem $item): int => $carry + (int) round(((float) $item->line_total) * 100),
            0,
        );

        $this->assertSame(
            (int) round(((float) $order->grand_total) * 100),
            $sumOfLines,
        );
    }

    #[Test]
    public function it_deducts_stock_by_the_ordered_quantity(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Stock Buyer', 'email' => 'stock@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function it_stores_the_price_and_tax_rate_that_applied_at_order_time(): void
    {
        $product = Product::factory()->priced('24.99', '18.00')->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Snapshot', 'email' => 'snapshot@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        // Reprice the product AFTER the order was placed.
        $product->update(['price' => '99.99', 'tax_rate' => '0.00']);

        $item = OrderItem::query()->firstOrFail();

        // The historic snapshot must be unaffected by the repricing.
        $this->assertSame('24.99', (string) $item->unit_price);
        $this->assertSame('18.00', (string) $item->tax_rate);
        $this->assertSame('24.99', (string) $item->line_subtotal);
        $this->assertSame('4.50', (string) $item->line_tax);
    }

    #[Test]
    public function it_ignores_client_supplied_prices_taxes_and_totals(): void
    {
        $product = Product::factory()->priced('48000.00', '18.00')->withStock(10)->create();

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Price Hacker', 'email' => 'hacker@example.test'],
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                // Everything below is hostile input and must be discarded.
                'unit_price' => '0.01',
                'price' => '0.01',
                'tax_rate' => '0.00',
                'line_subtotal' => '0.01',
                'line_tax' => '0.00',
                'line_total' => '0.01',
            ]],
            'subtotal' => '0.01',
            'tax_total' => '0.00',
            'grand_total' => '0.01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '48000.00')
            ->assertJsonPath('data.tax_total', '8640.00')
            ->assertJsonPath('data.grand_total', '56640.00')
            ->assertJsonPath('data.items.0.unit_price', '48000.00');

        $this->assertDatabaseHas('orders', ['grand_total' => '56640.00']);
    }

    #[Test]
    public function it_dispatches_a_confirmation_job_after_a_successful_order(): void
    {
        Queue::fake();

        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Queue Test', 'email' => 'queue@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::query()->firstOrFail();

        Queue::assertPushed(
            SendOrderConfirmation::class,
            static fn (SendOrderConfirmation $job): bool => $job->orderId === $order->id,
        );
    }
}
