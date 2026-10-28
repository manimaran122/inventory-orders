<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerOrderHistoryRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerOrderHistoryController extends Controller
{
    /**
     * GET /api/customers/orders?email=...
     *
     * Returns a paginated order history for the customer with the given email.
     */
    public function __invoke(CustomerOrderHistoryRequest $request): JsonResponse
    {
        $customer = Customer::query()
            ->withEmail($request->email())
            ->first();

        if ($customer === null) {
            // 404 with a neutral message. Deliberately does NOT distinguish
            // "no such customer" in a way that could be used to enumerate which
            // email addresses exist in the system beyond what the lookup
            // already implies.
            return response()->json([
                'message' => 'No customer found for the supplied email address.',
                'data' => null,
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $orders = $customer->orders()
            // Eager-loads items and their products, so rendering N orders
            // costs 3 queries rather than 1 + N + N. This is the N+1 fix.
            ->with(['items.product'])
            ->withCount('items')
            ->latest('created_at')
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return OrderResource::collection($orders)
            ->additional([
                'customer' => (new CustomerResource($customer))->toArray($request),
            ])
            ->response();
    }
}
