@props(['amount', 'muted' => false])

{{--
    Money is rendered from the decimal STRING the server produced, with
    tabular-nums so digits align vertically in a column.
--}}
<span @class(['tabular-nums whitespace-nowrap', 'text-slate-500' => $muted])>
    {{ config('inventory.money.currency_symbol') }}{{ number_format((float) $amount, 2) }}
</span>
