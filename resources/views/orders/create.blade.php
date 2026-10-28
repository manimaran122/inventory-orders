@extends('layouts.app')

@section('title', 'New order')
@section('heading', 'New order')
@section('subheading', 'Select products and quantities to create an order')

@section('content')
    {{--
        The order builder.

        Alpine manages the dynamic line rows and previews the totals as the user
        types. The preview is a CONVENIENCE ONLY - it is recalculated from the
        same product data the server holds, but the server independently
        computes the authoritative figures when the form is submitted and those
        are what get stored. A mismatch is impossible to exploit: nothing the
        browser computes is ever sent or trusted.
    --}}
    <form method="POST"
          action="{{ route('orders.store') }}"
          x-data="orderBuilder({
              products: {{ Js::from($products) }},
              oldRows: {{ Js::from(array_values(old('items', []))) }},
              currencySymbol: {{ Js::from(config('inventory.money.currency_symbol')) }},
          })"
          @submit="submitting = true"
          class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @csrf

        <div class="space-y-6 lg:col-span-2">
            {{-- Stock shortage errors come back under the `stock` key. --}}
            @error('stock')
                <div class="alert-error" role="alert">
                    <svg class="mt-0.5 size-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-medium">Not enough stock</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            @foreach((array) $errors->get('stock') as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @enderror

            {{-- Customer --}}
            <section class="card" aria-labelledby="customer-heading">
                <div class="card-header">
                    <h2 id="customer-heading" class="card-title">Customer</h2>
                </div>
                <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="customer_name" class="label">
                            Name <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input type="text"
                               id="customer_name"
                               name="customer_name"
                               value="{{ old('customer_name', $prefillCustomer?->name) }}"
                               required
                               autocomplete="name"
                               class="input"
                               @error('customer_name') aria-invalid="true" aria-describedby="customer_name_error" @enderror>
                        @error('customer_name')
                            <span id="customer_name_error"><x-input-error :messages="$message" /></span>
                        @enderror
                    </div>

                    <div>
                        <label for="customer_email" class="label">
                            Email <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input type="email"
                               id="customer_email"
                               name="customer_email"
                               value="{{ old('customer_email', $prefillCustomer?->email) }}"
                               required
                               autocomplete="email"
                               class="input"
                               @error('customer_email') aria-invalid="true" aria-describedby="customer_email_error" @enderror>
                        @error('customer_email')
                            <span id="customer_email_error"><x-input-error :messages="$message" /></span>
                        @enderror
                        <p class="help-text">
                            An existing customer with this email is reused; otherwise a new one is created.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Items --}}
            <section class="card" aria-labelledby="items-heading">
                <div class="card-header">
                    <h2 id="items-heading" class="card-title">Products</h2>
                    <button type="button" @click="addRow()" class="btn-secondary btn-sm">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add product
                    </button>
                </div>

                <div class="card-body space-y-3">
                    <template x-for="(row, index) in rows" :key="row.key">
                        <div class="rounded-lg border border-slate-200 p-3 sm:p-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:items-start">
                                {{-- Product select --}}
                                <div class="sm:col-span-7">
                                    <label class="label" :for="'product_' + index">Product</label>
                                    <select :id="'product_' + index"
                                            :name="'items[' + index + '][product_id]'"
                                            x-model="row.productId"
                                            class="input">
                                        <option value="">Choose a product...</option>
                                        <template x-for="product in products" :key="product.id">
                                            <option :value="product.id"
                                                    :disabled="isChosenElsewhere(product.id, index)"
                                                    x-text="productLabel(product) + (isChosenElsewhere(product.id, index) ? ' (already added)' : '')">
                                            </option>
                                        </template>
                                    </select>
                                    <p class="input-error" x-show="rowError(index, 'product_id')" x-cloak>
                                        <span x-text="rowError(index, 'product_id')"></span>
                                    </p>
                                </div>

                                {{-- Quantity --}}
                                <div class="sm:col-span-3">
                                    <label class="label" :for="'quantity_' + index">Quantity</label>
                                    <input type="number"
                                           :id="'quantity_' + index"
                                           :name="'items[' + index + '][quantity]'"
                                           x-model.number="row.quantity"
                                           min="1"
                                           :max="maxFor(row)"
                                           step="1"
                                           class="input"
                                           :aria-invalid="exceedsStock(row)">

                                    {{-- Live stock guidance --}}
                                    <template x-if="selected(row)">
                                        <p class="help-text" :class="exceedsStock(row) ? errorTextClass : ''">
                                            <span x-show="!exceedsStock(row)" x-text="stockLabel(row)"></span>
                                            <span x-show="exceedsStock(row)" x-cloak x-text="shortageLabel(row)"></span>
                                        </p>
                                    </template>
                                </div>

                                {{-- Remove --}}
                                <div class="flex items-end sm:col-span-2 sm:justify-end">
                                    <button type="button"
                                            @click="removeRow(index)"
                                            class="btn-ghost btn-sm text-red-600 hover:bg-red-50 hover:text-red-700"
                                            :disabled="rows.length === 1"
                                            :aria-label="removeLabel(index)">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                        </svg>
                                        Remove
                                    </button>
                                </div>
                            </div>

                            {{-- Per-line computed figures --}}
                            <template x-if="selected(row) && row.quantity > 0">
                                <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-xs">
                                    <div>
                                        <dt class="text-slate-500">Unit price</dt>
                                        <dd class="mt-0.5 font-medium tabular-nums text-slate-900" x-text="money(selected(row).price)"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Line subtotal</dt>
                                        <dd class="mt-0.5 font-medium tabular-nums text-slate-900" x-text="money(lineSubtotal(row))"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500" x-text="taxLabel(row)"></dt>
                                        <dd class="mt-0.5 font-medium tabular-nums text-slate-900" x-text="money(lineTax(row))"></dd>
                                    </div>
                                </dl>
                            </template>
                        </div>
                    </template>

                    <p class="text-xs text-slate-500">
                        A product can appear only once per order. Change the quantity on the
                        existing line instead of adding it twice.
                    </p>
                </div>
            </section>
        </div>

        {{-- Summary sidebar --}}
        <div class="lg:col-span-1">
            <section class="card sticky top-20" aria-labelledby="summary-heading">
                <div class="card-header">
                    <h2 id="summary-heading" class="card-title">Order summary</h2>
                </div>

                <div class="card-body space-y-3">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Items</dt>
                            <dd class="font-medium tabular-nums text-slate-900" x-text="itemCount()"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Subtotal</dt>
                            <dd class="font-medium tabular-nums text-slate-900" x-text="money(subtotal())"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Tax</dt>
                            <dd class="font-medium tabular-nums text-slate-900" x-text="money(taxTotal())"></dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-2.5">
                            <dt class="font-semibold text-slate-900">Grand total</dt>
                            <dd class="text-lg font-semibold tabular-nums text-slate-900" x-text="money(grandTotal())"></dd>
                        </div>
                    </dl>

                    <p class="text-xs text-slate-500">
                        This is a preview. The server recalculates every figure from the
                        product catalogue when the order is submitted.
                    </p>

                    {{-- Submit, with a loading state so a slow request cannot be
                         double-submitted. --}}
                    <button type="submit"
                            class="btn-primary w-full"
                            :disabled="submitting || !canSubmit()">
                        <svg x-show="submitting" x-cloak class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="submitLabel()"></span>
                    </button>

                    <p class="text-center text-xs text-slate-500" x-show="!canSubmit() && !submitting" x-cloak>
                        Choose at least one product and quantity.
                    </p>
                </div>
            </section>
        </div>
    </form>
@endsection
