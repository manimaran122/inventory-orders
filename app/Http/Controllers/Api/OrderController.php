<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * POST /api/orders
     *
     * Creates an order. All money is computed server-side from the product
     * catalogue; any price or total in the request body is ignored.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orders->create(
                customerName: $request->customerName(),
                customerEmail: $request->customerEmail(),
                lines: $request->lineItems(),
            );
        } catch (InsufficientStockException $e) {
            // 409 Conflict: the request was well-formed and the products are
            // real, but the current state of the world cannot satisfy it.
            // This is a different situation from a 422 malformed payload.
            return response()->json([
                'message' => 'One or more items do not have enough stock available.',
                'errors' => [
                    'stock' => $e->shortages,
                ],
            ], JsonResponse::HTTP_CONFLICT);
        } catch (ProductUnavailableException $e) {
            return response()->json([
                'message' => 'One or more products are no longer available.',
                'errors' => [
                    'products' => $e->productIds,
                ],
            ], JsonResponse::HTTP_CONFLICT);
        }

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
