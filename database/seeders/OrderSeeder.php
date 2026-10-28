<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\OrderLineInput;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Queue;

class OrderSeeder extends Seeder
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * Seed order history by driving the real OrderService.
     *
     * Going through the production code path (rather than inserting rows
     * directly) means seeded orders have correct totals, correct historic
     * price snapshots, and correctly decremented stock. Seed data can never
     * drift from the calculation rules this way.
     */
    public function run(): void
    {
        // Stop the seeder from flooding the queue with confirmation jobs.
        Queue::fake();

        $customers = Customer::query()
            // Leave this customer with no orders so the "customer exists but
            // has no history" response can be demonstrated.
            ->where('email', '!=', 'noah.lindqvist@example.test')
            ->get();

        if ($customers->isEmpty()) {
            return;
        }

        // Only products with enough stock to satisfy a few seeded orders.
        $products = Product::query()
            ->where('stock_quantity', '>=', 20)
            ->get();

        if ($products->count() < 2) {
            return;
        }

        foreach ($customers as $customer) {
            $orderCount = random_int(1, 3);

            for ($i = 0; $i < $orderCount; $i++) {
                // Distinct products only: duplicates in a single order are
                // rejected by design.
                $chosen = $products->random(min(random_int(1, 3), $products->count()));

                $lines = $chosen->map(fn (Product $product): OrderLineInput => new OrderLineInput(
                    productId: $product->id,
                    quantity: random_int(1, 3),
                ))->values()->all();

                try {
                    $order = $this->orders->create(
                        customerName: $customer->name,
                        customerEmail: $customer->email,
                        lines: $lines,
                    );

                    // Backdate so the history screens show a realistic spread
                    // of dates rather than every order sharing one timestamp.
                    $createdAt = now()->subDays(random_int(0, 60))->subHours(random_int(0, 23));

                    Order::query()->whereKey($order->id)->update([
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                } catch (\Throwable) {
                    // Stock ran out mid-seed; skip this order and continue.
                    continue;
                }
            }
        }
    }
}
