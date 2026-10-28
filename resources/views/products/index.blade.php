@extends('layouts.app')

@section('title', 'Products')
@section('heading', 'Products')
@section('subheading', $products->total() . ' ' . Str::plural('product', $products->total()) . ' in the catalogue')

@section('content')
    <div class="card">
        {{-- Filters. A GET form so the current filter state lives in the URL
             and is shareable/bookmarkable. --}}
        <div class="card-header">
            <form method="GET" action="{{ route('products.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 sm:max-w-xs">
                    <label for="search" class="label">Search</label>
                    <input type="search"
                           id="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Name or product code"
                           class="input">
                </div>

                <div class="w-full sm:w-44">
                    <label for="stock" class="label">Stock level</label>
                    <select id="stock" name="stock" class="input">
                        <option value="all" @selected($stockFilter === 'all')>All products</option>
                        <option value="low" @selected($stockFilter === 'low')>Low stock</option>
                        <option value="out" @selected($stockFilter === 'out')>Out of stock</option>
                        <option value="healthy" @selected($stockFilter === 'healthy')>Healthy stock</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">Apply</button>
                    @if($search || $stockFilter !== 'all')
                        <a href="{{ route('products.index') }}" class="btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        @if($products->isEmpty())
            <x-empty-state title="No products found"
                           :message="$search || $stockFilter !== 'all'
                                ? 'No product matches the current filters. Try widening your search.'
                                : 'The catalogue is empty. Run the database seeder to add sample products.'">
                @if($search || $stockFilter !== 'all')
                    <x-slot:action>
                        <a href="{{ route('products.index') }}" class="btn-secondary">Clear filters</a>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Product catalogue with price, tax rate and available stock</caption>
                    <thead>
                        <tr>
                            <th scope="col">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $sort === 'name' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                                   class="inline-flex items-center gap-1 hover:text-slate-700">
                                    Product
                                    @if($sort === 'name')
                                        <span aria-hidden="true">{{ $direction === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                        <span class="sr-only">sorted {{ $direction === 'asc' ? 'ascending' : 'descending' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="numeric">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'price', 'direction' => $sort === 'price' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                                   class="inline-flex items-center gap-1 hover:text-slate-700">
                                    Unit price
                                    @if($sort === 'price')
                                        <span aria-hidden="true">{{ $direction === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="numeric">Tax</th>
                            <th scope="col" class="numeric">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'stock_quantity', 'direction' => $sort === 'stock_quantity' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                                   class="inline-flex items-center gap-1 hover:text-slate-700">
                                    Stock
                                    @if($sort === 'stock_quantity')
                                        <span aria-hidden="true">{{ $direction === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                    @endif
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $product)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ $product->code }}</div>
                                </td>
                                <td class="numeric font-medium text-slate-900">
                                    <x-money :amount="$product->price" />
                                </td>
                                <td class="numeric text-slate-600">
                                    {{ rtrim(rtrim(number_format((float) $product->tax_rate, 2), '0'), '.') }}%
                                </td>
                                <td class="numeric">
                                    <x-stock-badge :quantity="$product->stock_quantity" :threshold="$threshold" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
