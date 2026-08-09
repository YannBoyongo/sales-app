<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($shopName = $appSetting?->shopname ?? config('app.name', 'Application'))
        <title>@yield('title') — {{ $shopName }}</title>

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|figtree:400,500,600&display=swap" rel="stylesheet" />

        @env('local')
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}">
        @endenv
    </head>
    <body class="font-sans antialiased text-slate-900">
        <div class="error-split">
            <aside class="error-hero" aria-hidden="true">
                <div
                    class="error-hero__media"
                    style="background-image: url('{{ asset('images/moto.jpg') }}');"
                ></div>
                <div class="error-hero__overlay"></div>

                <div class="error-hero__content">
                    <div class="error-hero__brand">
                        <span class="error-hero__mark">
                            {{ mb_strtoupper(mb_substr($shopName, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="error-hero__brand-name">{{ $shopName }}</p>
                            <p class="error-hero__eyebrow">Ventes · Stocks · Caisse</p>
                        </div>
                    </div>

                    <div class="error-hero__copy">
                        <p class="error-hero__code">@yield('code')</p>
                        <h1 class="error-hero__title">@yield('headline')</h1>
                        <p class="error-hero__lead">@yield('message')</p>
                    </div>

                    <p class="error-hero__footer">
                        © {{ date('Y') }} {{ $shopName }}
                    </p>
                </div>
            </aside>

            <main class="error-panel">
                <div class="error-panel__inner">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <span class="flex h-11 w-11 items-center justify-center rounded-none bg-primary text-lg font-bold text-white shadow-md shadow-primary/25">
                            {{ mb_strtoupper(mb_substr($shopName, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-slate-900">{{ $shopName }}</p>
                            <p class="text-xs text-slate-500">@yield('title')</p>
                        </div>
                    </div>

                    <div class="error-card">
                        <div class="error-card__badge">
                            @yield('icon')
                        </div>

                        <p class="error-card__code lg:hidden">@yield('code')</p>

                        <h2 class="error-card__title lg:hidden">@yield('headline')</h2>

                        <p class="error-card__message lg:hidden">@yield('message')</p>

                        @hasSection('hint')
                            <p class="error-card__hint">@yield('hint')</p>
                        @endif

                        <div class="error-card__actions">
                            @yield('actions')
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
