@extends('layouts.app')

@section('title', 'Customers')
@section('heading', 'Customers')
@section('subheading', $customers->total() . ' ' . Str::plural('customer', $customers->total()))

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('customers.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 sm:max-w-xs">
                    <label for="search" class="label">Search</label>
                    <input type="search"
                           id="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Name or email address"
                           class="input">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">Search</button>
                    @if($search)
                        <a href="{{ route('customers.index') }}" class="btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        @if($customers->isEmpty())
            <x-empty-state title="No customers found"
                           :message="$search
                                ? 'No customer matches that search.'
                                : 'Customers are created automatically when an order is placed.'" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Customers with their order counts and totals</caption>
                    <thead>
                        <tr>
                            <th scope="col">Customer</th>
                            <th scope="col" class="numeric">Orders</th>
                            <th scope="col" class="numeric">Total spend</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $customer->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $customer->email }}</div>
                                </td>
                                <td class="numeric">
                                    @if($customer->orders_count > 0)
                                        <span class="badge-neutral">{{ $customer->orders_count }}</span>
                                    @else
                                        <span class="text-slate-400">None</span>
                                    @endif
                                </td>
                                <td class="numeric font-medium text-slate-900">
                                    <x-money :amount="$customer->orders_sum_grand_total ?? 0" />
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('customers.show', $customer) }}" class="btn-secondary btn-sm">
                                        Order history
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $customers->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
