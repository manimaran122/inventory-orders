@props(['href', 'active' => false, 'icon' => null])

<a href="{{ $href }}"
   @class(['nav-link', 'nav-link-active' => $active])
   @if($active) aria-current="page" @endif>
    @if($icon)
        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            {{ $icon }}
        </svg>
    @endif
    <span>{{ $slot }}</span>
</a>
