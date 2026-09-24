<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Choisir une branche - {{ $appSetting?->shopname ?? config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @env('local')
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}">
            <script type="module" src="{{ Vite::asset('resources/js/app.js') }}"></script>
        @endenv
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
            <div class="mb-8 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary text-lg font-bold text-white shadow-md shadow-primary/25">
                    {{ mb_strtoupper(mb_substr($appSetting?->shopname ?? config('app.name', 'A'), 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="text-lg font-bold tracking-tight text-slate-900">{{ $appSetting?->shopname ?? config('app.name') }}</p>
                    <p class="text-xs font-medium text-slate-500">{{ auth()->user()?->name }}</p>
                </div>
            </div>

            <div class="w-full max-w-2xl rounded-xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
