<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} — {{ __('Authentication') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-8 sm:px-6">
            <a href="{{ route('login') }}" class="mb-8 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-lg font-bold text-white shadow-lg shadow-primary/30">
                    {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                </span>
                <span class="text-xl font-bold tracking-tight text-slate-900">{{ config('app.name') }}</span>
            </a>

            <div class="app-panel w-full max-w-md app-panel-body">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
