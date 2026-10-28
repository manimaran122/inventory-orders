@props(['quantity', 'threshold' => null])

@php
    $threshold ??= (int) config('inventory.low_stock_threshold');
    $quantity = (int) $quantity;

    // Three states rather than two, because "none left" and "nearly out" call
    // for different urgency from whoever is reading the screen.
    [$class, $label] = match (true) {
        $quantity === 0 => ['badge-danger', 'Out of stock'],
        $quantity <= $threshold => ['badge-warning', $quantity . ' left'],
        default => ['badge-success', $quantity . ' in stock'],
    };
@endphp

<span class="{{ $class }}">
    @if($quantity === 0)
        <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
        </svg>
    @elseif($quantity <= $threshold)
        <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
        </svg>
    @endif
    {{ $label }}
</span>
