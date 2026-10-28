@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Inventory and order overview')

@section('actions')
    <a href="{{ route('orders.create') }}" class="btn-primary">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New order</span>
    </a>
@endsection

@section('content')
    {{-- Summary cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total products" :value="number_format($productCount)" :href="route('products.index')">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Total customers" :value="number_format($customerCount)" :href="route('customers.index')">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Total orders" :value="number_format($orderCount)"
                     :hint="'Revenue ' . config('inventory.money.currency_symbol') . number_format((float) $revenueTotal, 2)"
                     :href="route('orders.index')">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Low stock products"
                     :value="number_format($lowStockCount)"
                     :tone="$lowStockCount > 0 ? 'danger' : 'success'"
                     :hint="$outOfStockCount > 0 ? $outOfStockCount . ' out of stock' : 'At or below ' . $threshold . ' units'"
                     :href="route('products.index', ['stock' => 'low'])">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        {{-- Low stock --}}
        <section class="card" aria-labelledby="low-stock-heading">
            <div class="card-header">
                <h2 id="low-stock-heading" class="card-title">Low stock</h2>
                <a href="{{ route('products.index', ['stock' => 'low']) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                    View all
                </a>
            </div>

            @if($lowStockProducts->isEmpty())
                <x-empty-state title="Everything is well stocked"
                               :message="'No product is at or below ' . $threshold . ' units.'" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <caption class="sr-only">Products at or below the low stock threshold</caption>
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col" class="numeric">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowStockProducts as $product)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $product->code }}</div>
                                    </td>
                                    <td class="numeric">
                                        <x-stock-badge :quantity="$product->stock_quantity" :threshold="$threshold" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Recent orders --}}
        <section class="card" aria-labelledby="recent-orders-heading">
            <div class="card-header">
                <h2 id="recent-orders-heading" class="card-title">Recent orders</h2>
                <a href="{{ route('orders.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                    View all
                </a>
            </div>

            @if($recentOrders->isEmpty())
                <x-empty-state title="No orders yet"
                               message="Orders will appear here once the first one is placed.">
                    <x-slot:action>
                        <a href="{{ route('orders.create') }}" class="btn-primary">Create the first order</a>
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <caption class="sr-only">The most recent orders</caption>
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col">Customer</th>
                                <th scope="col" class="numeric">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="font-medium text-brand-600 hover:text-brand-700">
                                            {{ $order->reference }}
                                        </a>
                                        <div class="text-xs text-slate-500">
                                            {{ $order->created_at?->format('j M Y') }} &middot;
                                            {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-slate-900">{{ $order->customer->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $order->customer->email }}</div>
                                    </td>
                                    <td class="numeric font-medium text-slate-900">
                                        <x-money :amount="$order->grand_total" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
