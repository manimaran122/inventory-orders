<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LowStockProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();
    }

    #[Test]
    public function it_returns_products_below_the_given_threshold(): void
    {
        Product::factory()->withStock(2)->create(['name' => 'Very Low']);
        Product::factory()->withStock(4)->create(['name' => 'Low']);
        Product::factory()->withStock(50)->create(['name' => 'Healthy']);

        $response = $this->getJson('/api/products/low-stock?threshold=5');

        $response->assertOk()->assertJsonCount(2, 'data');

        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Very Low', $names);
        $this->assertContains('Low', $names);
        $this->assertNotContains('Healthy', $names);
    }

    #[Test]
    public function a_product_exactly_at_the_threshold_is_included(): void
    {
        // Documented behaviour: the comparison is <=, so a product sitting
        // exactly on the threshold counts as low stock.
        Product::factory()->withStock(10)->create(['name' => 'Exactly Ten']);
        Product::factory()->withStock(11)->create(['name' => 'Eleven']);

        $response = $this->getJson('/api/products/low-stock?threshold=10');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Exactly Ten', $response->json('data.0.name'));
    }

    #[Test]
    public function products_above_the_threshold_are_excluded(): void
    {
        Product::factory()->count(3)->withStock(100)->create();

        $this->getJson('/api/products/low-stock?threshold=5')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function out_of_stock_products_are_included(): void
    {
        Product::factory()->outOfStock()->create(['name' => 'Gone']);

        $this->getJson('/api/products/low-stock?threshold=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock_quantity', 0);
    }

    #[Test]
    public function it_uses_the_configured_default_threshold_when_none_is_given(): void
    {
        config(['inventory.low_stock_threshold' => 10]);

        Product::factory()->withStock(9)->create(['name' => 'Under Default']);
        Product::factory()->withStock(10)->create(['name' => 'At Default']);
        Product::factory()->withStock(11)->create(['name' => 'Over Default']);

        $response = $this->getJson('/api/products/low-stock');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('filters.threshold', 10)
            ->assertJsonPath('filters.is_default_threshold', true);
    }

    #[Test]
    public function it_reports_a_custom_threshold_as_not_default(): void
    {
        $this->getJson('/api/products/low-stock?threshold=3')
            ->assertOk()
            ->assertJsonPath('filters.threshold', 3)
            ->assertJsonPath('filters.is_default_threshold', false);
    }

    #[Test]
    public function a_threshold_of_zero_returns_only_out_of_stock_products(): void
    {
        Product::factory()->outOfStock()->create(['name' => 'Zero']);
        Product::factory()->withStock(1)->create(['name' => 'One']);

        $response = $this->getJson('/api/products/low-stock?threshold=0');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Zero', $response->json('data.0.name'));
    }

    #[Test]
    #[DataProvider('invalidThresholds')]
    public function it_rejects_invalid_thresholds(mixed $threshold): void
    {
        $this->getJson('/api/products/low-stock?threshold='.urlencode((string) $threshold))
            ->assertStatus(422)
            ->assertJsonValidationErrors('threshold');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidThresholds(): array
    {
        return [
            'negative' => [-1],
            'far negative' => [-9999],
            'non numeric' => ['abc'],
            'fractional' => [1.5],
            'above the configured maximum' => [99999999],
            'sql injection attempt' => ['1; DROP TABLE products'],
        ];
    }

    #[Test]
    public function the_results_are_ordered_with_the_lowest_stock_first(): void
    {
        Product::factory()->withStock(5)->create();
        Product::factory()->withStock(1)->create();
        Product::factory()->withStock(3)->create();

        $response = $this->getJson('/api/products/low-stock?threshold=10');

        $stock = array_column($response->json('data'), 'stock_quantity');

        $this->assertSame([1, 3, 5], $stock);
    }

    #[Test]
    public function it_flags_each_product_with_a_low_stock_indicator(): void
    {
        config(['inventory.low_stock_threshold' => 10]);

        Product::factory()->withStock(2)->create();

        $this->getJson('/api/products/low-stock?threshold=5')
            ->assertOk()
            ->assertJsonPath('data.0.is_low_stock', true);
    }

    #[Test]
    public function it_paginates_results(): void
    {
        Product::factory()->count(5)->withStock(1)->create();

        $this->getJson('/api/products/low-stock?threshold=5&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }
}
