@extends('layouts.app')

@section('title', 'Order ' . $order->reference)
@section('heading', 'Order ' . $order->reference)
@section('subheading', 'Placed ' . $order->created_at?->format('j M Y, H:i'))

@section('actions')
    <div class="flex gap-2">
        <a href="{{ route('orders.index') }}" class="btn-secondary">Back</a>
        <a href="{{ route('orders.create') }}" class="btn-primary">
            <span class="hidden sm:inline">New order</span>
            <span class="sm:hidden">New</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Items --}}
            <section class="card" aria-labelledby="items-heading">
                <div class="card-header">
                    <h2 id="items-heading" class="card-title">Items</h2>
                    <span class="badge-neutral">
                        {{ $order->items->count() }} {{ Str::plural('line', $order->items->count()) }}
                    </span>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <caption class="sr-only">Line items for this order</caption>
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col" class="numeric">Qty</th>
                                <th scope="col" class="numeric">Unit price</th>
                                <th scope="col" class="numeric">Subtotal</th>
                                <th scope="col" class="numeric">Tax</th>
                                <th scope="col" class="numeric">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">
                                            {{ $item->product?->name ?? 'Product removed' }}
                                        </div>
                                        <div class="font-mono text-xs text-slate-500">
                                            {{ $item->product?->code ?? 'n/a' }}
                                        </div>
                                    </td>
                                    <td class="numeric">{{ $item->quantity }}</td>
                                    <td class="numeric"><x-money :amount="$item->unit_price" /></td>
                                    <td class="numeric"><x-money :amount="$item->line_subtotal" /></td>
                                    <td class="numeric text-slate-600">
                                        <x-money :amount="$item->line_tax" />
                                        <div class="text-xs text-slate-400">
                                            {{ rtrim(rtrim(number_format((float) $item->tax_rate, 2), '0'), '.') }}%
                                        </div>
                                    </td>
                                    <td class="numeric font-medium text-slate-900">
                                        <x-money :amount="$item->line_total" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-5 py-3">
                    <p class="text-xs text-slate-500">
                        Unit prices and tax rates shown are those that applied when the order was
                        placed, and do not change if the product is repriced later.
                    </p>
                </div>
            </section>
        </div>

        {{-- Summary + customer --}}
        <div class="space-y-6 lg:col-span-1">
            <section class="card" aria-labelledby="totals-heading">
                <div class="card-header">
                    <h2 id="totals-heading" class="card-title">Totals</h2>
                </div>
                <div class="card-body">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Subtotal</dt>
                            <dd class="font-medium text-slate-900"><x-money :amount="$order->subtotal" /></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Tax</dt>
                            <dd class="font-medium text-slate-900"><x-money :amount="$order->tax_total" /></dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-2.5">
                            <dt class="font-semibold text-slate-900">Grand total</dt>
                            <dd class="text-lg font-semibold text-slate-900">
                                <x-money :amount="$order->grand_total" />
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="card" aria-labelledby="customer-heading">
                <div class="card-header">
                    <h2 id="customer-heading" class="card-title">Customer</h2>
                </div>
                <div class="card-body space-y-3">
                    <div>
                        <p class="font-medium text-slate-900">{{ $order->customer->name }}</p>
                        <p class="text-sm text-slate-500">{{ $order->customer->email }}</p>
                    </div>
                    <a href="{{ route('customers.show', $order->customer) }}" class="btn-secondary btn-sm w-full">
                        View order history
                    </a>
                </div>
            </section>

            <section class="card" aria-labelledby="meta-heading">
                <div class="card-header">
                    <h2 id="meta-heading" class="card-title">Details</h2>
                </div>
                <div class="card-body">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-600">Reference</dt>
                            <dd class="font-mono text-xs text-slate-900">{{ $order->reference }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-600">Order ID</dt>
                            <dd class="text-slate-900">#{{ $order->id }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-600">Placed</dt>
                            <dd class="text-right text-slate-900">{{ $order->created_at?->format('j M Y, H:i') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-600">Confirmation</dt>
                            <dd><span class="badge-success">Queued</span></dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>
    </div>
@endsection
