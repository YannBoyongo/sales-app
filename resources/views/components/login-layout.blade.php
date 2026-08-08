<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Connexion - {{ $appSetting?->shopname ?? config('app.name', 'Laravel') }}</title>

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
    <body class="font-sans antialiased text-slate-900">
        <div class="login-split">
            {{-- Left hero --}}
            <aside class="login-hero" aria-hidden="false">
                <div
                    class="login-hero__media"
                    style="background-image: url('{{ asset('images/moto.jpg') }}');"
                ></div>
                <div class="login-hero__overlay"></div>

                <div class="login-hero__content">
                    <div class="login-hero__brand">
                        <span class="login-hero__mark" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr($appSetting?->shopname ?? config('app.name', 'A'), 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="login-hero__brand-name">{{ $appSetting?->shopname ?? config('app.name') }}</p>
                            <p class="login-hero__eyebrow">Ventes · Stocks · Caisse</p>
                        </div>
                    </div>

                    <div class="login-hero__copy">
                        <p class="login-hero__badge">Gestion commerciale</p>
                        <h1 class="login-hero__title">
                            Pilotez vos ventes et vos stocks en toute clarté.
                        </h1>
                        <p class="login-hero__lead">
                            Une plateforme unique pour suivre les points de vente, les réceptions, les transferts et la performance de chaque branche.
                        </p>
                    </div>

                    <div class="login-hero__stats">
                        <div>
                            <p class="login-hero__stat-value">Temps réel</p>
                            <p class="login-hero__stat-label">Suivi des ventes et de la caisse</p>
                        </div>
                        <div>
                            <p class="login-hero__stat-value">Multi-sites</p>
                            <p class="login-hero__stat-label">Branches et emplacements unifiés</p>
                        </div>
                        <div>
                            <p class="login-hero__stat-value">Stock sûr</p>
                            <p class="login-hero__stat-label">Réceptions, transferts, alertes</p>
                        </div>
                    </div>

                    <p class="login-hero__footer">
                        © {{ date('Y') }} {{ $appSetting?->shopname ?? config('app.name') }}
                    </p>
                </div>
            </aside>

            {{-- Right form panel --}}
            <main class="login-panel">
                <div class="login-panel__inner">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
