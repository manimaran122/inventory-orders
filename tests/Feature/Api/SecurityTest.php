<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();
    }

    #[Test]
    public function the_product_code_unique_constraint_is_enforced(): void
    {
        Product::factory()->create(['code' => 'SKU-DUPLICATE']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['code' => 'SKU-DUPLICATE']);
    }

    #[Test]
    public function the_customer_email_unique_constraint_is_enforced(): void
    {
        Customer::factory()->create(['email' => 'duplicate@example.test']);

        $this->expectException(QueryException::class);

        Customer::factory()->create(['email' => 'duplicate@example.test']);
    }

    #[Test]
    public function an_order_cannot_reference_a_customer_that_does_not_exist(): void
    {
        $this->expectException(QueryException::class);

        Order::query()->create([
            'reference' => 'ORD-BROKEN-FK',
            'customer_id' => 999999,
            'subtotal' => '1.00',
            'tax_total' => '0.00',
            'grand_total' => '1.00',
        ]);
    }

    #[Test]
    public function a_product_referenced_by_an_order_cannot_be_deleted(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'FK Test', 'email' => 'fk@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        // restrictOnDelete protects the historic record of what was sold.
        $this->expectException(QueryException::class);

        $product->delete();
    }

    #[Test]
    public function a_customer_with_orders_cannot_be_deleted(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'FK Test', 'email' => 'fkcust@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $customer = Customer::query()->where('email', 'fkcust@example.test')->firstOrFail();

        $this->expectException(QueryException::class);

        $customer->delete();
    }

    #[Test]
    public function deleting_an_order_removes_its_line_items(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Cascade', 'email' => 'cascade@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseCount('order_items', 1);

        Order::query()->firstOrFail()->delete();

        // cascadeOnDelete: a line item cannot outlive its parent order.
        $this->assertDatabaseCount('order_items', 0);
    }

    #[Test]
    public function products_are_protected_against_mass_assignment(): void
    {
        $product = Product::factory()->create();
        $originalId = $product->id;

        // `id` is not in $fillable, so this must be silently ignored rather
        // than letting a request reassign the primary key.
        $product->fill(['id' => 999999, 'name' => 'Renamed']);

        $this->assertSame($originalId, $product->id);
        $this->assertSame('Renamed', $product->name);
    }

    #[Test]
    public function orders_are_protected_against_mass_assignment_of_timestamps(): void
    {
        $customer = Customer::factory()->create();

        $order = Order::query()->create([
            'reference' => 'ORD-MASS-ASSIGN',
            'customer_id' => $customer->id,
            'subtotal' => '1.00',
            'tax_total' => '0.00',
            'grand_total' => '1.00',
            // Not fillable; must be ignored.
            'created_at' => '1999-01-01 00:00:00',
        ]);

        $this->assertNotSame('1999', $order->created_at?->format('Y'));
    }

    #[Test]
    public function sql_injection_in_the_email_parameter_is_treated_as_data(): void
    {
        Customer::factory()->withEmail('real@example.test')->create();

        // A classic injection payload. Because queries are parameterised it is
        // treated as a (malformed) email value, never as SQL.
        $this->getJson('/api/customers/orders?email='.urlencode("' OR '1'='1"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // The customers table is still intact.
        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function sql_injection_in_the_threshold_parameter_is_rejected(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products/low-stock?threshold='.urlencode('1; DROP TABLE products'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('threshold');

        $this->assertDatabaseCount('products', 3);
    }

    #[Test]
    public function error_responses_do_not_leak_internal_details(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Probe', 'email' => 'not-an-email'],
            'items' => [],
        ]);

        $response->assertStatus(422);

        $body = $response->getContent() ?: '';

        // None of these should ever appear in a client-facing error.
        foreach ([
            '/var/www',
            'vendor/laravel',
            'Illuminate\Database',
            'DB_PASSWORD',
            'APP_KEY',
            'SQLSTATE',
            '.php:',
        ] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $body,
                "Error response leaked internal detail: {$needle}",
            );
        }
    }

    #[Test]
    public function a_validation_error_response_has_a_consistent_shape(): void
    {
        $this->postJson('/api/orders', [
            'customer' => ['name' => 'X', 'email' => 'bad'],
            'items' => [],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    #[Test]
    public function stock_quantity_cannot_be_set_negative_at_the_database_level(): void
    {
        $product = Product::factory()->withStock(5)->create();

        // The column is UNSIGNED, so the database itself refuses a negative
        // value. This is the final backstop behind the application checks.
        $this->expectException(QueryException::class);

        $product->update(['stock_quantity' => -1]);
    }
}
