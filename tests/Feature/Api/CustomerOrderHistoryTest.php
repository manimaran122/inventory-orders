<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();
    }

    /**
     * Place a real order through the API so history data is produced by the
     * production code path rather than hand-built rows.
     */
    private function placeOrder(string $email, string $name = 'History Buyer'): void
    {
        $product = Product::factory()->priced('10.00', '10.00')->withStock(100)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => $name, 'email' => $email],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();
    }

    #[Test]
    public function it_returns_the_order_history_for_an_existing_customer(): void
    {
        $this->placeOrder('history@example.test');
        $this->placeOrder('history@example.test');

        $response = $this->getJson('/api/customers/orders?email=history@example.test');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('customer.email', 'history@example.test')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'reference', 'subtotal', 'tax_total', 'grand_total', 'created_at',
                    'items' => [['id', 'quantity', 'unit_price', 'line_total', 'product' => ['id', 'name', 'code']]],
                ]],
                'links',
                'meta',
                'customer' => ['id', 'name', 'email'],
            ]);
    }

    #[Test]
    public function it_returns_an_empty_list_for_a_customer_with_no_orders(): void
    {
        Customer::factory()->withEmail('noorders@example.test')->create();

        $this->getJson('/api/customers/orders?email=noorders@example.test')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('customer.email', 'noorders@example.test');
    }

    #[Test]
    public function it_returns_404_for_an_unknown_email(): void
    {
        $this->getJson('/api/customers/orders?email=nobody@example.test')
            ->assertNotFound()
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function it_rejects_a_malformed_email(): void
    {
        $this->getJson('/api/customers/orders?email=not-an-email')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_rejects_a_missing_email(): void
    {
        $this->getJson('/api/customers/orders')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_matches_the_customer_case_insensitively(): void
    {
        $this->placeOrder('case.test@example.test');

        $this->getJson('/api/customers/orders?email=Case.Test@EXAMPLE.TEST')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_only_returns_orders_belonging_to_the_requested_customer(): void
    {
        $this->placeOrder('mine@example.test');
        $this->placeOrder('theirs@example.test');
        $this->placeOrder('theirs@example.test');

        $response = $this->getJson('/api/customers/orders?email=mine@example.test');

        $response->assertOk()->assertJsonCount(1, 'data');

        $customer = Customer::query()->where('email', 'mine@example.test')->firstOrFail();

        $this->assertSame($customer->id, $response->json('customer.id'));
    }

    #[Test]
    public function it_paginates_the_history(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->placeOrder('paged@example.test');
        }

        $this->getJson('/api/customers/orders?email=paged@example.test&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    #[Test]
    public function it_rejects_an_out_of_range_page_size(): void
    {
        Customer::factory()->withEmail('paged@example.test')->create();

        $this->getJson('/api/customers/orders?email=paged@example.test&per_page=5000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    #[Test]
    public function it_does_not_trigger_n_plus_one_queries(): void
    {
        // Six orders, each with its own product.
        for ($i = 0; $i < 6; $i++) {
            $this->placeOrder('nplusone@example.test');
        }

        DB::enableQueryLog();

        $this->getJson('/api/customers/orders?email=nplusone@example.test&per_page=50')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Without eager loading this would be roughly 1 + 6 + 6 queries. The
        // generous ceiling keeps the test about the N+1 pattern rather than an
        // exact query count that framework changes would churn.
        $this->assertLessThan(
            12,
            $queryCount,
            "Expected eager loading to keep the query count low; ran {$queryCount} queries."
        );
    }
}
