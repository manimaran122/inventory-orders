@extends('layouts.app')

@section('title', 'Order history')
@section('heading', 'Order history')
@section('subheading', $orders->total() . ' ' . Str::plural('order', $orders->total()))

@section('actions')
    <a href="{{ route('orders.create') }}" class="btn-primary">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New order</span>
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('orders.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 sm:max-w-xs">
                    <label for="search" class="label">Search</label>
                    <input type="search"
                           id="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Reference, customer or email"
                           class="input">
                </div>

                <div>
                    <label for="from" class="label">From</label>
                    <input type="date" id="from" name="from" value="{{ $from }}" class="input">
                </div>

                <div>
                    <label for="to" class="label">To</label>
                    <input type="date" id="to" name="to" value="{{ $to }}" class="input">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">Filter</button>
                    @if($search || $from || $to)
                        <a href="{{ route('orders.index') }}" class="btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        @if($orders->isEmpty())
            <x-empty-state title="No orders found"
                           :message="$search || $from || $to
                                ? 'No order matches the current filters.'
                                : 'No orders have been placed yet.'">
                <x-slot:action>
                    @if($search || $from || $to)
                        <a href="{{ route('orders.index') }}" class="btn-secondary">Clear filters</a>
                    @else
                        <a href="{{ route('orders.create') }}" class="btn-primary">Create the first order</a>
                    @endif
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">All orders with customer, date and totals</caption>
                    <thead>
                        <tr>
                            <th scope="col">Reference</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Date</th>
                            <th scope="col" class="numeric">Items</th>
                            <th scope="col" class="numeric">Subtotal</th>
                            <th scope="col" class="numeric">Tax</th>
                            <th scope="col" class="numeric">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('orders.show', $order) }}"
                                       class="font-mono text-xs font-medium text-brand-600 hover:text-brand-700">
                                        {{ $order->reference }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('customers.show', $order->customer) }}"
                                       class="text-slate-900 hover:text-brand-600">
                                        {{ $order->customer->name }}
                                    </a>
                                    <div class="text-xs text-slate-500">{{ $order->customer->email }}</div>
                                </td>
                                <td class="whitespace-nowrap text-slate-600">
                                    {{ $order->created_at?->format('j M Y') }}
                                    <div class="text-xs text-slate-400">{{ $order->created_at?->format('H:i') }}</div>
                                </td>
                                <td class="numeric">{{ $order->items_count }}</td>
                                <td class="numeric text-slate-600"><x-money :amount="$order->subtotal" /></td>
                                <td class="numeric text-slate-600"><x-money :amount="$order->tax_total" /></td>
                                <td class="numeric font-semibold text-slate-900"><x-money :amount="$order->grand_total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
