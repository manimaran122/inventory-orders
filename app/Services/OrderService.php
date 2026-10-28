<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CalculatedLine;
use App\Data\OrderLineInput;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductUnavailableException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Creates orders atomically and safely.
 *
 * The three guarantees this class exists to provide
 * -------------------------------------------------
 * 1. MONEY IS SERVER-AUTHORITATIVE. Prices and tax rates are read from the
 *    products table inside the transaction. Anything the client sent about
 *    price, tax or totals is ignored entirely - it never even reaches here.
 *
 * 2. STOCK CANNOT OVERSELL. Product rows are locked with SELECT ... FOR UPDATE
 *    before stock is read, so a second concurrent request blocks until the
 *    first commits and then observes the already-decremented figure. Rows are
 *    locked in ascending id order so two orders touching the same products in
 *    different sequences cannot deadlock against each other.
 *
 * 3. NOTHING IS HALF-WRITTEN. Customer, order, lines and stock decrements all
 *    happen in one transaction. Any failure rolls the whole thing back, and the
 *    confirmation job is only queued after the commit succeeds.
 */
final class OrderService
{
    public function __construct(
        private readonly OrderCalculator $calculator,
    ) {}

    /**
     * @param  list<OrderLineInput>  $lines
     *
     * @throws InsufficientStockException
     * @throws ProductUnavailableException
     */
    public function create(string $customerName, string $customerEmail, array $lines): Order
    {
        $order = DB::transaction(function () use ($customerName, $customerEmail, $lines): Order {
            $customer = $this->resolveCustomer($customerName, $customerEmail);

            $products = $this->lockProducts($lines);

            $this->assertStockIsSufficient($lines, $products);

            $calculated = $this->calculator->calculate($lines, $products);

            $order = Order::create([
                'reference' => Order::generateReference(),
                'customer_id' => $customer->id,
                'subtotal' => $calculated->subtotal->toDecimalString(),
                'tax_total' => $calculated->taxTotal->toDecimalString(),
                'grand_total' => $calculated->grandTotal->toDecimalString(),
            ]);

            foreach ($calculated->lines as $line) {
                $order->items()->create($line->toDatabaseRow());
            }

            $this->decrementStock($calculated->lines);

            return $order;
        });

        // Dispatched only after the transaction has committed. If the closure
        // above had thrown, we would never reach this line, so a rolled-back
        // order can never produce a confirmation.
        SendOrderConfirmation::dispatch($order->id);

        return $order->load(['customer', 'items.product']);
    }

    /**
     * Find the customer by email, or create them.
     *
     * Email is normalised (trimmed + lower-cased) so "Bob@Example.com" and
     * "bob@example.com" resolve to the same customer rather than creating a
     * duplicate. The unique index on customers.email is what ultimately
     * guarantees this, even under simultaneous first-time orders.
     */
    private function resolveCustomer(string $name, string $email): Customer
    {
        $normalised = Customer::normaliseEmail($email);

        $customer = Customer::query()->where('email', $normalised)->first();

        if ($customer !== null) {
            // The stored name of an existing customer is left untouched: an
            // order request is not an identity-change request, and silently
            // overwriting it would let anyone who knows an email address
            // rename that customer.
            return $customer;
        }

        return Customer::create([
            'name' => $name,
            'email' => $normalised,
        ]);
    }

    /**
     * Load and row-lock every product in the order.
     *
     * @param  list<OrderLineInput>  $lines
     * @return Collection<int, Product> keyed by product id
     *
     * @throws ProductUnavailableException
     */
    private function lockProducts(array $lines): Collection
    {
        $productIds = array_map(static fn (OrderLineInput $l): int => $l->productId, $lines);

        // Deterministic ordering is the deadlock-avoidance strategy: every
        // transaction in the system acquires product locks in ascending id
        // order, so two concurrent orders can never each hold a lock that the
        // other one is waiting for.
        sort($productIds);

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $missing = array_values(array_diff($productIds, $products->keys()->all()));

        if ($missing !== []) {
            throw new ProductUnavailableException($missing);
        }

        return $products;
    }

    /**
     * Re-check stock AFTER the lock is held.
     *
     * This re-check is the point of the whole exercise. Validation already
     * checked stock, but that read happened outside the lock and may be stale
     * by the time we get here.
     *
     * @param  list<OrderLineInput>  $lines
     * @param  Collection<int, Product>  $products
     *
     * @throws InsufficientStockException
     */
    private function assertStockIsSufficient(array $lines, Collection $products): void
    {
        $shortages = [];

        foreach ($lines as $line) {
            $product = $products->get($line->productId);

            if (! $product instanceof Product) {
                throw new LogicException(
                    "Product {$line->productId} was not loaded for the stock check."
                );
            }

            if ($product->stock_quantity < $line->quantity) {
                $shortages[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'requested' => $line->quantity,
                    'available' => $product->stock_quantity,
                ];
            }
        }

        if ($shortages !== []) {
            // Throwing inside the transaction closure rolls back the customer
            // row and anything else written so far.
            throw new InsufficientStockException($shortages);
        }
    }

    /**
     * Decrement stock for each line.
     *
     * The conditional UPDATE (where stock_quantity >= quantity) is a second
     * line of defence: even in the theoretical case of the lock being
     * ineffective, the database refuses to write a negative figure, and a zero
     * affected-row count surfaces as an exception that rolls the order back.
     *
     * @param  list<CalculatedLine>  $lines
     */
    private function decrementStock(array $lines): void
    {
        foreach ($lines as $line) {
            $affected = Product::query()
                ->whereKey($line->productId)
                ->where('stock_quantity', '>=', $line->quantity)
                ->decrement('stock_quantity', $line->quantity);

            if ($affected !== 1) {
                throw new InsufficientStockException([[
                    'product_id' => $line->productId,
                    'product_name' => (string) Product::query()
                        ->whereKey($line->productId)
                        ->value('name'),
                    'requested' => $line->quantity,
                    'available' => (int) Product::query()
                        ->whereKey($line->productId)
                        ->value('stock_quantity'),
                ]]);
            }
        }
    }
}
