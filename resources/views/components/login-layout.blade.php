<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Connexion — {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-[20px] text-neutral-900">
        <div class="min-h-screen flex flex-col lg:flex-row">
            {{-- Panneau formulaire --}}
            <div class="relative z-10 flex w-full flex-1 flex-col justify-center bg-white px-8 py-12 sm:px-12 lg:w-[46%] lg:max-w-none lg:flex-none lg:px-14 xl:px-20">
                <div class="mx-auto w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>

            {{-- Panneau visuel + diagonale --}}
            <div class="relative hidden min-h-[40vh] flex-1 lg:block lg:min-h-screen">
                <div
                    class="login-hero-diagonal absolute inset-0 bg-neutral-200 bg-cover bg-center"
                    style="background-image: url('{{ asset('login-bg.jpg') }}');"
                    aria-hidden="true"
                ></div>
            </div>
        </div>
    </body>
</html>
