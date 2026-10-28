@props(['label', 'value', 'tone' => 'neutral', 'hint' => null, 'href' => null])

@php
    $toneClasses = match ($tone) {
        'danger' => 'bg-red-50 text-red-600',
        'warning' => 'bg-amber-50 text-amber-600',
        'success' => 'bg-emerald-50 text-emerald-600',
        default => 'bg-brand-50 text-brand-600',
    };
@endphp

<div class="card p-5">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="stat-label">{{ $label }}</p>
            <p class="stat-value mt-2">{{ $value }}</p>
            @if($hint)
                <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
            @endif
        </div>

        @isset($icon)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $toneClasses }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    {{ $icon }}
                </svg>
            </span>
        @endisset
    </div>

    @if($href)
        <a href="{{ $href }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
            View all
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
            </svg>
        </a>
    @endif
</div>
