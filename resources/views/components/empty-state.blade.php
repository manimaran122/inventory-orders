@props(['title', 'message' => null])

{{--
    Shown whenever a list has no rows. An explicit empty state (rather than a
    blank table) tells the user nothing is broken and suggests what to do next.
--}}
<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <span class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
        </svg>
    </span>

    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</h3>

    @if($message)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
