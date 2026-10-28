<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $threshold = (int) config('inventory.low_stock_threshold');

        return view('dashboard.index', [
            'threshold' => $threshold,
            'productCount' => Product::query()->count(),
            'customerCount' => Customer::query()->count(),
            'orderCount' => Order::query()->count(),
            'lowStockCount' => Product::query()->lowStock($threshold)->count(),
            'outOfStockCount' => Product::query()->where('stock_quantity', 0)->count(),

            // Revenue is summed in SQL rather than in PHP so the database does
            // the aggregation over DECIMAL columns.
            'revenueTotal' => (string) (Order::query()->sum('grand_total') ?: '0.00'),

            'lowStockProducts' => Product::query()
                ->lowStock($threshold)
                ->orderBy('stock_quantity')
                ->orderBy('name')
                ->limit(8)
                ->get(),

            'recentOrders' => Order::query()
                // Eager loads to keep the dashboard at a constant query count
                // regardless of how many orders are listed.
                ->with('customer')
                ->withCount('items')
                ->latest('created_at')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
