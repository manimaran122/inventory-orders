@extends('layouts.app')

@section('title', $customer->name)
@section('heading', $customer->name)
@section('subheading', $customer->email)

@section('actions')
    <div class="flex gap-2">
        <a href="{{ route('customers.index') }}" class="btn-secondary">Back</a>
        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="btn-primary">
            <span class="hidden sm:inline">New order</span>
            <span class="sm:hidden">Order</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Total orders" :value="number_format($orders->total())" />
        <x-stat-card label="Total spend"
                     :value="config('inventory.money.currency_symbol') . number_format((float) $customer->orders()->sum('grand_total'), 2)" />
        <x-stat-card label="Customer since" :value="$customer->created_at?->format('M Y') ?? 'Unknown'" />
    </div>

    <section class="card mt-6" aria-labelledby="history-heading">
        <div class="card-header">
            <h2 id="history-heading" class="card-title">Order history</h2>
        </div>

        @if($orders->isEmpty())
            <x-empty-state title="No orders yet"
                           :message="$customer->name . ' has not placed an order yet.'">
                <x-slot:action>
                    <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="btn-primary">
                        Create an order
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($orders as $order)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('orders.show', $order) }}"
                                   class="font-medium text-brand-600 hover:text-brand-700">
                                    {{ $order->reference }}
                                </a>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $order->created_at?->format('j M Y, H:i') }} &middot;
                                    {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}
                                </p>
                            </div>

                            <div class="text-right">
                                <div class="font-semibold text-slate-900">
                                    <x-money :amount="$order->grand_total" />
                                </div>
                                <div class="text-xs text-slate-500">
                                    incl. <x-money :amount="$order->tax_total" /> tax
                                </div>
                            </div>
                        </div>

                        {{-- Line items, collapsed by default to keep the list scannable. --}}
                        <details class="group mt-3">
                            <summary class="cursor-pointer text-xs font-medium text-slate-500 hover:text-slate-700">
                                Show items
                            </summary>
                            <div class="table-wrap mt-2 rounded-lg border border-slate-200">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Product</th>
                                            <th scope="col" class="numeric">Qty</th>
                                            <th scope="col" class="numeric">Unit price</th>
                                            <th scope="col" class="numeric">Line total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                            <tr>
                                                <td>
                                                    <div class="text-slate-900">{{ $item->product?->name ?? 'Product removed' }}</div>
                                                    <div class="font-mono text-xs text-slate-500">{{ $item->product?->code ?? 'n/a' }}</div>
                                                </td>
                                                <td class="numeric">{{ $item->quantity }}</td>
                                                <td class="numeric"><x-money :amount="$item->unit_price" /></td>
                                                <td class="numeric font-medium"><x-money :amount="$item->line_total" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </li>
                @endforeach
            </ul>

            @if($orders->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection
