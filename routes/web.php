<?php

declare(strict_types=1);

use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| The browser UI. These routes are session-based and CSRF protected by
| Laravel's default `web` middleware group.
|
| Authentication: the UI is intentionally open in this build - see SECURITY.md
| for the reasoning and for what to add before exposing it beyond localhost.
| The JSON API under /api is separately protected by Sanctum tokens.
|
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');

Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

// `orders/create` is declared before `orders/{order}` so the literal segment is
// not captured as a route parameter.
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
