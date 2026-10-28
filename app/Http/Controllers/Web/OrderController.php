<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreOrderWebRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * Order history list, with filters.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $orders = Order::query()
            ->with('customer')
            ->withCount('items')
            ->when($validated['search'] ?? null, function ($query, string $term): void {
                $escaped = addcslashes(trim($term), '%_'.chr(92));

                $query->where(function ($q) use ($escaped): void {
                    $q->where('reference', 'like', "%{$escaped}%")
                        ->orWhereHas('customer', function ($c) use ($escaped): void {
                            $c->where('name', 'like', "%{$escaped}%")
                                ->orWhere('email', 'like', "%{$escaped}%");
                        });
                });
            })
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'search' => $validated['search'] ?? '',
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
        ]);
    }

    /**
     * The order builder form.
     */
    public function create(Request $request): View
    {
        return view('orders.create', [
            // Only sellable products are offered, so the form cannot be used to
            // build an order that is guaranteed to fail.
            'products' => Product::query()
                ->where('stock_quantity', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'price', 'tax_rate', 'stock_quantity']),

            // Prefill support for "order again" from a customer page.
            'prefillCustomer' => $request->filled('customer')
                ? Customer::query()->find($request->integer('customer'))
                : null,
        ]);
    }

    /**
     * Create the order, then redirect to the confirmation screen.
     */
    public function store(StoreOrderWebRequest $request): RedirectResponse
    {
        try {
            $order = $this->orders->create(
                customerName: trim((string) $request->validated('customer_name')),
                customerEmail: trim((string) $request->validated('customer_email')),
                lines: $request->lineItems(),
            );
        } catch (InsufficientStockException $e) {
            // Rebuild the form with the user input intact and a precise message
            // naming each product that fell short.
            $messages = array_map(
                static fn (array $s): string => sprintf(
                    '%s: you asked for %d but only %d %s available.',
                    $s['product_name'],
                    $s['requested'],
                    $s['available'],
                    $s['available'] === 1 ? 'is' : 'are',
                ),
                $e->shortages,
            );

            return back()
                ->withInput()
                ->withErrors(['stock' => $messages]);
        } catch (ProductUnavailableException) {
            return back()
                ->withInput()
                ->withErrors(['stock' => 'One or more selected products are no longer available.']);
        }

        // Redirect-after-POST so a browser refresh cannot resubmit the order.
        return redirect()
            ->route('orders.show', $order)
            ->with('success', "Order {$order->reference} was created successfully.");
    }

    /**
     * Order confirmation / detail screen.
     */
    public function show(Order $order): View
    {
        // Eager load before rendering so the item table does not fire a query
        // per line.
        $order->load(['customer', 'items.product']);

        return view('orders.show', [
            'order' => $order,
        ]);
    }
}
