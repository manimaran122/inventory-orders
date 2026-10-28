<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LowStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class LowStockProductController extends Controller
{
    /**
     * GET /api/products/low-stock?threshold=...
     *
     * Products whose stock is at or below the threshold. Without a threshold
     * parameter the configured default is used.
     */
    public function __invoke(LowStockRequest $request): JsonResponse
    {
        $threshold = $request->threshold();

        $products = Product::query()
            ->lowStock($threshold)
            // Lowest stock first: the most urgent items lead the list.
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->paginate($request->perPage())
            ->withQueryString();

        // The applied threshold is reported under its own top-level key rather
        // than inside `meta`: a paginated resource collection builds its own
        // `meta` block, and merging into it here would be silently overwritten.
        return ProductResource::collection($products)
            ->additional([
                'filters' => [
                    // Echoing the applied threshold makes the response
                    // self-describing: the caller can see whether their value
                    // or the configured default was used.
                    'threshold' => $threshold,
                    'is_default_threshold' => $request->validated('threshold') === null,
                ],
            ])
            ->response();
    }
}
