<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Genuine concurrency tests.
 *
 * Why these do not use RefreshDatabase
 * ------------------------------------
 * RefreshDatabase wraps each test in a transaction that is rolled back at the
 * end. That transaction is invisible to any other connection, so data created
 * by the test would not exist for a competing process. These tests therefore
 * manage their own data and clean up in tearDown.
 *
 * Why real processes
 * ------------------
 * Two sequential requests inside one PHP process can never contend for a row
 * lock, so a sequential test proves nothing about concurrency. These tests
 * fork actual OS processes with pcntl_fork so two independent database
 * connections race for the same row at the same moment.
 *
 * Requires: MySQL/MariaDB (InnoDB row locking) and ext-pcntl. Both are
 * asserted at the start of each test and the test is skipped with a clear
 * message when unavailable, rather than passing silently.
 */
#[Group('concurrency')]
class OrderConcurrencyTest extends TestCase
{
    private ?User $apiUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'Concurrency tests require MySQL/MariaDB for InnoDB row-level locking. '
                .'SQLite locks the whole database file and cannot demonstrate '
                .'SELECT ... FOR UPDATE contention.'
            );
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped(
                'Concurrency tests require ext-pcntl to fork competing processes.'
            );
        }

        // Build the schema once, without the per-test transaction that
        // RefreshDatabase would use.
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->apiUser = User::factory()->create();
        $this->actingAsApiUser($this->apiUser);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('order_items')->delete();
        DB::table('orders')->delete();
        DB::table('products')->delete();
        DB::table('customers')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    #[Test]
    public function exactly_one_of_two_simultaneous_orders_wins_the_last_unit(): void
    {
        $product = Product::factory()->withStock(1)->create();
        $productId = $product->id;

        $results = $this->raceForStock($productId, quantity: 1, racers: 2);

        // The whole point: one success, one clean failure.
        $this->assertSame(
            1,
            $results['created'],
            "Expected exactly 1 successful order, got {$results['created']}. "
            .'More than one means stock was oversold.'
        );
        $this->assertSame(
            1,
            $results['rejected'],
            'Expected exactly 1 rejected order.'
        );

        // Stock landed on zero, never below.
        $this->assertSame(0, (int) DB::table('products')->where('id', $productId)->value('stock_quantity'));
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function stock_never_goes_negative_under_heavy_contention(): void
    {
        // 10 processes each try to buy 1 unit, but only 4 units exist.
        $product = Product::factory()->withStock(4)->create();
        $productId = $product->id;

        $results = $this->raceForStock($productId, quantity: 1, racers: 10);

        $this->assertSame(
            4,
            $results['created'],
            "Expected exactly 4 successful orders for 4 units of stock, got {$results['created']}."
        );
        $this->assertSame(6, $results['rejected']);

        $remaining = (int) DB::table('products')->where('id', $productId)->value('stock_quantity');

        $this->assertSame(0, $remaining);
        $this->assertGreaterThanOrEqual(0, $remaining, 'Stock must never go negative.');
        $this->assertSame(4, Order::query()->count());
    }

    #[Test]
    public function concurrent_orders_for_multiple_products_do_not_deadlock(): void
    {
        // Two products, and two racers that reference them in OPPOSITE order in
        // the request body. Because the service sorts product ids before
        // locking, both transactions acquire locks in the same sequence and
        // cannot deadlock.
        $first = Product::factory()->withStock(5)->create();
        $second = Product::factory()->withStock(5)->create();

        $pairs = [
            [$first->id, $second->id],
            [$second->id, $first->id],
        ];

        $children = [];

        foreach ($pairs as $index => $ids) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Could not fork a child process.');
            }

            if ($pid === 0) {
                // Child: a fresh connection is essential, otherwise the child
                // shares and corrupts the parent database socket.
                DB::purge();

                $exitCode = 1;

                try {
                    $response = $this->postJson('/api/orders', [
                        'customer' => [
                            'name' => "Racer {$index}",
                            'email' => "racer{$index}@example.test",
                        ],
                        'items' => [
                            ['product_id' => $ids[0], 'quantity' => 1],
                            ['product_id' => $ids[1], 'quantity' => 1],
                        ],
                    ]);

                    $exitCode = $response->getStatusCode() === 201 ? 0 : 2;
                } catch (Throwable) {
                    $exitCode = 3;
                }

                // exit() avoids running the parent test teardown in the child.
                exit($exitCode);
            }

            $children[] = $pid;
        }

        $created = 0;
        $other = 0;

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $code = pcntl_wexitstatus($status);

            if ($code === 0) {
                $created++;
            } else {
                $other++;
            }
        }

        // Both have ample stock, so both must succeed. A deadlock would show up
        // as a failure here.
        $this->assertSame(2, $created, 'Both orders should succeed; a deadlock or error occurred.');
        $this->assertSame(0, $other);

        $this->assertSame(3, (int) DB::table('products')->where('id', $first->id)->value('stock_quantity'));
        $this->assertSame(3, (int) DB::table('products')->where('id', $second->id)->value('stock_quantity'));
    }

    /**
     * Fork N processes that all try to buy the same product at the same time.
     *
     * Each child signals its outcome through its exit code, which is the only
     * reliable channel back to the parent across a fork.
     *
     * @return array{created:int, rejected:int, errored:int}
     */
    private function raceForStock(int $productId, int $quantity, int $racers): array
    {
        $children = [];

        for ($i = 0; $i < $racers; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Could not fork a child process.');
            }

            if ($pid === 0) {
                // Child process.
                DB::purge();

                $exitCode = 3;

                try {
                    $response = $this->postJson('/api/orders', [
                        'customer' => [
                            'name' => "Racer {$i}",
                            'email' => "racer{$i}@example.test",
                        ],
                        'items' => [
                            ['product_id' => $productId, 'quantity' => $quantity],
                        ],
                    ]);

                    $exitCode = match ($response->getStatusCode()) {
                        201 => 0,   // order created
                        409 => 1,   // cleanly rejected for insufficient stock
                        default => 2,
                    };
                } catch (Throwable) {
                    $exitCode = 3;
                }

                exit($exitCode);
            }

            $children[] = $pid;
        }

        $created = 0;
        $rejected = 0;
        $errored = 0;

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);

            match (pcntl_wexitstatus($status)) {
                0 => $created++,
                1 => $rejected++,
                default => $errored++,
            };
        }

        $this->assertSame(
            0,
            $errored,
            "{$errored} racer(s) failed with an unexpected error rather than a clean 409."
        );

        return ['created' => $created, 'rejected' => $rejected, 'errored' => $errored];
    }
}
