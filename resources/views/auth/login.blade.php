<x-login-layout>
    <div class="mb-8 lg:mb-10">
        <div class="mb-6 flex items-center gap-3 lg:hidden">
            <span class="flex h-11 w-11 items-center justify-center rounded-none bg-primary text-lg font-bold text-white shadow-md shadow-primary/25">
                {{ mb_strtoupper(mb_substr($appSetting?->shopname ?? config('app.name', 'A'), 0, 1)) }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-base font-semibold text-slate-900">{{ $appSetting?->shopname ?? config('app.name') }}</p>
                <p class="text-xs text-slate-500">Connexion sécurisée</p>
            </div>
        </div>

        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
            Bon retour
        </h1>
        <p class="mt-2 text-sm text-slate-500 sm:text-base">
            Connectez-vous à votre compte
            <span class="font-medium text-slate-700">{{ $appSetting?->shopname ?? config('app.name') }}</span>
            pour continuer.
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false }">
        @csrf

        <div>
            <label for="login" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                E-mail ou nom d’utilisateur
            </label>
            <input
                id="login"
                type="text"
                name="login"
                value="{{ old('login') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="vous@exemple.com"
                class="mt-2 block w-full rounded-lg border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
            />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-3">
                <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Mot de passe
                </label>
                @if (Route::has('password.request'))
                    <a
                        href="{{ route('password.request') }}"
                        class="text-xs font-medium text-primary hover:text-primary-hover"
                    >
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>
            <div class="relative mt-2">
                <input
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="block w-full rounded-lg border-slate-200 bg-white px-3 py-2.5 pr-11 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                >
                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg x-cloak x-show="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L3 3m13.5 13.5L21 21m-4.272-4.272A6 6 0 019.272 9.272" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-slate-300 text-primary shadow-sm focus:ring-primary"
                    name="remember"
                >
                <span class="text-sm text-slate-600">Rester connecté</span>
            </label>
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-md shadow-primary/25 transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
            Se connecter
        </button>
    </form>
</x-login-layout>
