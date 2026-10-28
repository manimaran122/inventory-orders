<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CustomerOrderHistoryController;
use App\Http\Controllers\Api\LowStockProductController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Every route is prefixed with /api automatically.
|
| PUBLIC:    POST /api/auth/token  (issuing a token obviously cannot itself
|                                   require a token; it is rate limited
|                                   instead)
| PROTECTED: everything else requires a valid Sanctum Bearer token.
|
*/

// Tighter throttle on the credential endpoint than on the rest of the API.
Route::post('/auth/token', [AuthTokenController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('api.auth.token.store');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::delete('/auth/token', [AuthTokenController::class, 'destroy'])
        ->name('api.auth.token.destroy');

    Route::get('/user', fn (Request $request) => $request->user())
        ->name('api.user');

    // API 1 - create an order.
    Route::post('/orders', [OrderController::class, 'store'])
        ->name('api.orders.store');

    // API 2 - customer order history by email.
    Route::get('/customers/orders', CustomerOrderHistoryController::class)
        ->name('api.customers.orders');

    // API 3 - low stock products.
    Route::get('/products/low-stock', LowStockProductController::class)
        ->name('api.products.low-stock');
});
