<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|figtree:400,500,600&display=swap" rel="stylesheet" />

        @env('local')
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}">
            <script type="module" src="{{ Vite::asset('resources/js/app.js') }}"></script>
        @endenv
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex bg-slate-100" x-data="{ sidebarOpen: false }">
            {{-- Mobile overlay --}}
            <div
                x-cloak
                x-show="sidebarOpen"
                x-transition.opacity
                :class="sidebarOpen ? 'pointer-events-auto' : 'pointer-events-none'"
                class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                @click="sidebarOpen = false"
                style="display: none;"
            ></div>

            <aside
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed lg:static inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r border-sidebar-border bg-white text-slate-800 shadow-card transition-transform duration-200 ease-out lg:transition-none"
            >
                <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-4">
                    <a href="{{ route('dashboard') }}" class="min-w-0">
                        <p class="truncate text-lg font-bold tracking-tight text-primary">{{ $appSetting?->shopname ?? config('app.name') }}</p>
                        @if ($appSetting?->phone)
                            <p class="truncate text-xs text-sidebar-muted">{{ $appSetting->phone }}</p>
                        @endif
                    </a>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 lg:hidden" @click="sidebarOpen = false" aria-label="Fermer le menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4 text-sm font-medium" @click="if (window.matchMedia('(max-width: 1023px)').matches && $event.target.closest('a')) sidebarOpen = false">
                    <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'app-nav-link-active' : '' }}">
                        <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6A2.25 2.25 0 016 3.75h3A2.25 2.25 0 0111.25 6v3A2.25 2.25 0 019 11.25H6A2.25 2.25 0 013.75 9V6zM12.75 6A2.25 2.25 0 0115 3.75h3A2.25 2.25 0 0120.25 6v3A2.25 2.25 0 0118 11.25h-3a2.25 2.25 0 01-2.25-2.25V6zM3.75 15A2.25 2.25 0 016 12.75h3a2.25 2.25 0 012.25 2.25v3a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25v-3zM12.75 15a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25v3A2.25 2.25 0 0118 20.25h-3a2.25 2.25 0 01-2.25-2.25v-3z"/></svg>
                        <span>Tableau de bord</span>
                    </a>
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('pending-actions.index') }}" class="app-nav-link {{ request()->routeIs('pending-actions.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zm9-3.758A9 9 0 1112 3a9 9 0 019 8.242z"/></svg>
                            <span>Pending actions</span>
                        </a>
                    @endif
                    @if (Auth::user()->canManageApplication())
                        <p class="app-nav-section">Structure</p>
                        <a href="{{ route('branches.index') }}" class="app-nav-link {{ request()->routeIs('branches.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m2.25-18v18m13.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.108c.621 0 1.125.504 1.125 1.125v8.25m-15-12h.108c.621 0 1.125.504 1.125 1.125v8.25M9.75 9.75h.007v.008H9.75V9.75zm0 3h.007v.008H9.75V12.75zm0 3h.007v.008H9.75v-.008z"/></svg>
                            <span>Branches</span>
                        </a>
                        <a href="{{ route('departments.index') }}" class="app-nav-link {{ request()->routeIs('departments.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6.878V6h12v.878A2.25 2.25 0 0115.75 9h-7.5A2.25 2.25 0 016 6.878zM15.75 15.75v-6h-7.5v6h7.5zM6 12h.75v6H6v-6zM18 12h-.75v6H18v-6zM6.75 3h10.5a.75.75 0 01.75.75v2.356a.75.75 0 01-.207.53L17.25 9H6.75l-1.068-1.364a.75.75 0 01-.207-.53V3.75a.75.75 0 01.75-.75z"/></svg>
                            <span>Départements</span>
                        </a>
                    @endif
                    <a href="{{ route('products.index') }}" class="app-nav-link {{ request()->routeIs('products.*') ? 'app-nav-link-active' : '' }}">
                        <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 8.25h12.974c.576 0 1.059.435 1.119 1.007zM8.625 8.25a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                        <span>Produits</span>
                    </a>

                    <p class="app-nav-section">Stock</p>
                    <a href="{{ route('stocks.index') }}" class="app-nav-link {{ request()->routeIs('stocks.*') ? 'app-nav-link-active' : '' }}">
                        <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                        <span>Stocks par emplacement</span>
                    </a>
                    @if (Auth::user()->canViewStockTransfers())
                        <a href="{{ route('stock-transfers.index') }}" class="app-nav-link {{ request()->routeIs('stock-transfers.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Transfert de stock</span>
                        </a>
                    @endif
                    <a href="{{ route('stock-movements.index') }}" class="app-nav-link {{ request()->routeIs('stock-movements.*') ? 'app-nav-link-active' : '' }}">
                        <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                        <span>Mouvements de stock</span>
                    </a>
                    <a href="{{ route('purchase-orders.index') }}" class="app-nav-link {{ request()->routeIs('purchase-orders.*') ? 'app-nav-link-active' : '' }}">
                        <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5A1.5 1.5 0 014.5 6h15A1.5 1.5 0 0121 7.5v9A1.5 1.5 0 0119.5 18h-15A1.5 1.5 0 013 16.5v-9zM7.5 10.5h9m-9 3h6"/></svg>
                        <span>Bons de commande</span>
                    </a>

                    @if (Auth::user()->canAccessPosSales() || Auth::user()->canAccessCashDeskFinanceFeatures())
                        <p class="app-nav-section">Caisse</p>
                    @endif
                    @if (Auth::user()->canAccessPosSales())
                        <a href="{{ route('sales.entry') }}" class="app-nav-link {{ request()->routeIs(['sales.entry', 'sales.choose-terminal', 'sales.choose-department', 'sales.create', 'sales.store', 'pos-terminal.workspace', 'pos-terminal.shifts.open', 'pos-terminal.shifts.close-review', 'pos-terminal.shifts.close']) ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.25M2.25 18.75V5.25m0 13.5h15.75m-15.75 0v.75A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25v-.75m-18 0h18M18 12h.008v.008H18V12zm-3 0h.008v.008H15V12zm3-3h.008v.008H18V9zm-3 0h.008v.008H15V9z"/></svg>
                            <span>Terminal POS</span>
                        </a>
                    @endif
                    @if (Auth::user()->canAccessPosSales() || Auth::user()->canAccessCashDeskFinanceFeatures())
                        <a href="{{ route('sales.overview') }}" class="app-nav-link {{ request()->routeIs('sales.overview') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.75v-.75h-.75v.75zm0 6H4.5v-.75h-.75v.75zm0 5.25h.75v-.75h-.75v.75z"/></svg>
                            <span>Toutes les ventes</span>
                        </a>
                    @endif
                    @if (Auth::user()->canAccessCashDeskFinanceFeatures())
                        <a href="{{ route('pos-terminal.shifts.closed') }}" class="app-nav-link {{ request()->routeIs('pos-terminal.shifts.closed*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m-3-13v4m6 13H6a2 2 0 01-2-2V7a2 2 0 012-2h3.5a1 1 0 00.8-.4l.9-1.2a1 1 0 01.8-.4H18a2 2 0 012 2v13a2 2 0 01-2 2z"/></svg>
                            <span>Shifts fermés</span>
                        </a>
                        <a href="{{ route('caution.index') }}" class="app-nav-link {{ request()->routeIs('caution.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a2.25 2.25 0 01-2.25 2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 013 12m18 0V8.25A2.25 2.25 0 0018.75 6H5.25A2.25 2.25 0 003 8.25V12m18 0v3.75A2.25 2.25 0 0118.75 18H5.25A2.25 2.25 0 013 15.75V12"/></svg>
                            <span>Caution</span>
                        </a>
                    @endif
                    @if (Auth::user()->canAccessClientsNav() && ! Auth::user()->canAccessCashDeskFinanceFeatures())
                        <p class="app-nav-section">Clients</p>
                        <a href="{{ route('clients.index') }}" class="app-nav-link {{ request()->routeIs('clients.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.964 0a9 9 0 10-11.964 0m11.964 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Clients</span>
                        </a>
                    @endif
                    @if (Auth::user()->canAccessCashDeskFinanceFeatures())
                        <p class="app-nav-section">Finances</p>
                        <a href="{{ route('clients.index') }}" class="app-nav-link {{ request()->routeIs('clients.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.964 0a9 9 0 10-11.964 0m11.964 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Clients</span>
                        </a>
                        @if (Auth::user()->canAccessAccounting())
                            <a href="{{ route('accounting.index') }}" class="app-nav-link {{ request()->routeIs('accounting.*') ? 'app-nav-link-active' : '' }}">
                                <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 3v17.25A.75.75 0 004.5 21h15m-12-3.75h2.25v-6H7.5v6zm4.5 0h2.25V9H12v8.25zm4.5 0h2.25V6h-2.25v11.25z"/></svg>
                                <span>Comptabilité</span>
                            </a>
                        @endif
                        <a href="{{ route('cash-vouchers.index') }}" class="app-nav-link {{ request()->routeIs('cash-vouchers.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 7.5h7.5M8.25 11.25h7.5M8.25 15h4.5m6.75-9.75v13.5a2.25 2.25 0 01-2.25 2.25h-10.5a2.25 2.25 0 01-2.25-2.25V5.25A2.25 2.25 0 016.75 3h10.5a2.25 2.25 0 012.25 2.25z"/></svg>
                            <span>Bon de caisse</span>
                        </a>
                        @if (Auth::user()->canAccessAccounting())
                            <a href="{{ route('chart-of-accounts.index') }}" class="app-nav-link {{ request()->routeIs('chart-of-accounts.*') ? 'app-nav-link-active' : '' }}">
                                <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15"/></svg>
                                <span>Plan comptable</span>
                            </a>
                        @endif
                    @endif
                    @if (Auth::user()->canManageApplication())
                        <p class="app-nav-section">Configuration</p>
                        <a href="{{ route('parametre.edit') }}" class="app-nav-link {{ request()->routeIs('parametre.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317a1.724 1.724 0 013.35 0l.214.72a1.724 1.724 0 002.591 1.02l.659-.38a1.724 1.724 0 012.355.632 1.724 1.724 0 01-.632 2.355l-.659.38a1.724 1.724 0 000 2.98l.659.38a1.724 1.724 0 01.632 2.355 1.724 1.724 0 01-2.355.632l-.659-.38a1.724 1.724 0 00-2.591 1.02l-.214.72a1.724 1.724 0 01-3.35 0l-.214-.72a1.724 1.724 0 00-2.591-1.02l-.659.38a1.724 1.724 0 01-2.355-.632 1.724 1.724 0 01.632-2.355l.659-.38a1.724 1.724 0 000-2.98l-.659-.38a1.724 1.724 0 01-.632-2.355 1.724 1.724 0 012.355-.632l.659.38a1.724 1.724 0 002.591-1.02l.214-.72z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            <span>Paramètre</span>
                        </a>
                        <a href="{{ route('users.index') }}" class="app-nav-link {{ request()->routeIs('users.*') ? 'app-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                            <span>Gestion des utilisateurs</span>
                        </a>
                    @endif
                </nav>

                <div class="border-t border-sidebar-border p-3 text-sm">
                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium text-slate-900">{{ Auth::user()->name }}</div>
                            <div class="truncate text-[10px] font-semibold uppercase tracking-wide text-sidebar-muted">
                                {{ Auth::user()->roles()->pluck('name')->implode(' · ') ?: 'Aucun rôle' }}
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-col gap-0.5">
                        <a href="{{ route('profile.edit') }}" class="app-nav-link">
                            <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            <span>Profil</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="app-nav-link w-full text-left">
                                <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                                <span>Déconnexion</span>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 shadow-sm backdrop-blur-md lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" class="rounded-lg border border-slate-200 p-2 text-slate-700 transition hover:bg-slate-50 lg:hidden" @click="sidebarOpen = true" aria-label="Ouvrir le menu">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        @isset($header)
                            <div class="min-w-0 truncate text-base font-semibold text-slate-900 lg:text-lg">
                                {{ $header }}
                            </div>
                        @endisset
                    </div>
                    <div class="hidden items-center gap-2 sm:flex">
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            {{ Auth::user()->roles()->pluck('name')->first() ?? 'Utilisateur' }}
                        </span>
                    </div>
                </header>

                <main class="flex-1 p-4 lg:p-8">
                    @php
                        $alerts = [
                            'success' => session('success'),
                            'warning' => session('warning'),
                            'danger' => session('danger') ?? session('error'),
                        ];

                        $alertStyles = [
                            'success' => [
                                'wrapper' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                                'icon' => 'M9 12.75l2.25 2.25L15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0z',
                            ],
                            'warning' => [
                                'wrapper' => 'border-amber-200 bg-amber-50 text-amber-900',
                                'icon' => 'M12 9v3.75m0 3.75h.007v.008H12v-.008zm0-13.5L2.25 19.5h19.5L12 3z',
                            ],
                            'danger' => [
                                'wrapper' => 'border-red-200 bg-red-50 text-red-900',
                                'icon' => 'M12 9v3.75m0 3.75h.007v.008H12v-.008zm9-3.758A9 9 0 1112 3a9 9 0 019 8.242z',
                            ],
                        ];
                    @endphp

                    @foreach ($alerts as $type => $message)
                        @if ($message)
                            <div class="app-alert-{{ $type === 'danger' ? 'danger' : $type }}" role="alert">
                                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $alertStyles[$type]['icon'] }}" />
                                </svg>
                                <p class="font-medium">{{ $message }}</p>
                            </div>
                        @endif
                    @endforeach

                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
