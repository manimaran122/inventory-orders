<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    {{--
        Layout: a fixed sidebar on desktop, collapsing to a slide-over drawer on
        mobile. `x-data` holds only the drawer state, so the shell needs no
        JavaScript beyond toggling one boolean.
    --}}
    <div x-data="{ sidebarOpen: false }" class="min-h-full">

        {{-- Skip link: the first thing a keyboard user tabs to, letting them
             jump past the navigation straight to the content. --}}
        <a href="#main-content"
           class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50
                  focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">
            Skip to main content
        </a>

        {{-- Mobile backdrop --}}
        <div x-show="sidebarOpen"
             x-transition.opacity
             @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
             aria-hidden="true"
             style="display: none"></div>

        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-50 w-64 transform border-r border-slate-200 bg-white
                      transition-transform duration-200 lg:translate-x-0"
               :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               aria-label="Main navigation">
            <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                        IO
                    </span>
                    <span class="text-sm font-semibold text-slate-900">Inventory &amp; Orders</span>
                </a>
                <button type="button"
                        @click="sidebarOpen = false"
                        class="btn-ghost -mr-2 p-2 lg:hidden"
                        aria-label="Close navigation">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <nav class="space-y-1 p-3">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </x-slot:icon>
                    Dashboard
                </x-nav-link>

                <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </x-slot:icon>
                    Products
                </x-nav-link>

                <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </x-slot:icon>
                    Customers
                </x-nav-link>

                <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.index') || request()->routeIs('orders.show')">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </x-slot:icon>
                    Order history
                </x-nav-link>

                <div class="pt-3">
                    <a href="{{ route('orders.create') }}" class="btn-primary w-full">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        New order
                    </a>
                </div>
            </nav>
        </aside>

        {{-- Main column --}}
        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
                <button type="button"
                        @click="sidebarOpen = true"
                        class="btn-ghost -ml-2 p-2 lg:hidden"
                        aria-label="Open navigation">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-semibold text-slate-900">@yield('heading', 'Dashboard')</h1>
                    @hasSection('subheading')
                        <p class="truncate text-sm text-slate-500">@yield('subheading')</p>
                    @endif
                </div>

                @yield('actions')
            </header>

            <main id="main-content" class="p-4 sm:p-6">
                @include('partials.flash')

                @yield('content')
            </main>

            <footer class="px-4 pb-8 pt-4 sm:px-6">
                <p class="text-xs text-slate-400">
                    {{ config('app.name') }} &middot; Laravel {{ Illuminate\Foundation\Application::VERSION }} &middot; PHP {{ PHP_VERSION }}
                </p>
            </footer>
        </div>
    </div>
</body>
</html>
