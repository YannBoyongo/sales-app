<x-login-layout>
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="mb-10 flex items-start gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary text-2xl font-bold text-white shadow-sm">
            {{ mb_substr(config('app.name', 'A'), 0, 1) }}
        </div>
        <div class="min-w-0 pt-0.5">
            <p class="text-[22px] font-semibold tracking-tight text-neutral-900">{{ config('app.name') }}</p>
            <p class="mt-2 max-w-sm text-[18px] font-normal leading-relaxed text-neutral-500">
                Pilotez ventes, stocks et sessions — une vision claire de votre activité.
            </p>
        </div>
    </div>

    <h1 class="mb-8 text-[24px] font-medium text-neutral-800">
        Système de gestion des stocks
    </h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="login" value="E-mail ou nom d'utilisateur" class="text-[18px] text-neutral-600" />
            <x-text-input
                id="login"
                class="mt-2 block w-full rounded-lg border-neutral-200 shadow-sm"
                type="text"
                name="login"
                :value="old('login')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" class="text-[18px] text-neutral-600" />
            <x-text-input
                id="password"
                class="mt-2 block w-full rounded-lg border-neutral-200 shadow-sm"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-neutral-300 text-primary shadow-sm focus:ring-primary"
                    name="remember"
                >
                <span class="ms-2 text-[18px] text-neutral-600">Se souvenir de moi</span>
            </label>
        </div>

        <x-primary-button class="mt-2 w-full justify-center rounded-lg py-3 !text-[20px] !font-semibold !normal-case !tracking-normal">
            Se connecter
        </x-primary-button>

        @if (Route::has('password.request'))
            <p class="pt-2 text-center text-[18px] text-neutral-600">
                <a
                    class="font-medium text-primary underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded"
                    href="{{ route('password.request') }}"
                >
                    Mot de passe oublié ?
                </a>
            </p>
        @endif
    </form>
</x-login-layout>
