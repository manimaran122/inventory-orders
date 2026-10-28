<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        // Validated inline: these are simple optional filters, so a dedicated
        // Form Request would add indirection without adding safety. The values
        // are still validated rather than trusted.
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', 'string', 'in:all,low,out,healthy'],
            'sort' => ['nullable', 'string', 'in:name,code,price,stock_quantity'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $threshold = (int) config('inventory.low_stock_threshold');
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $stockFilter = $validated['stock'] ?? 'all';

        $products = Product::query()
            ->search($validated['search'] ?? null)
            ->when($stockFilter === 'low', fn ($q) => $q->lowStock($threshold)->where('stock_quantity', '>', 0))
            ->when($stockFilter === 'out', fn ($q) => $q->where('stock_quantity', 0))
            ->when($stockFilter === 'healthy', fn ($q) => $q->where('stock_quantity', '>', $threshold))
            // $sort and $direction are constrained by the `in:` rules above, so
            // they are a safe allow list rather than arbitrary user input
            // reaching the ORDER BY clause.
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'threshold' => $threshold,
            'search' => $validated['search'] ?? '',
            'stockFilter' => $stockFilter,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
