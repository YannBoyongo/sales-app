<x-app-layout>
    <x-slot name="header">Modifier le compte — {{ $chartOfAccount->account_code }}</x-slot>

    <x-caisse-flow max-width="max-w-xl" :with-card="false">
        <x-slot name="header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Finances</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">Modifier le compte</h1>
                <p class="mt-2 text-sm text-neutral-600">Code <span class="font-mono font-semibold">{{ $chartOfAccount->account_code }}</span></p>
            </div>
        </x-slot>

        <form action="{{ route('chart-of-accounts.update', $chartOfAccount) }}" method="POST" class="space-y-4 rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5">
            @csrf
            @method('PATCH')

            <div>
                <label for="account_code" class="block text-xs font-semibold text-neutral-700">Code du compte</label>
                <input
                    id="account_code"
                    name="account_code"
                    type="text"
                    value="{{ old('account_code', $chartOfAccount->account_code) }}"
                    required
                    maxlength="30"
                    class="mt-1 block w-full rounded-lg border-neutral-300 font-mono text-sm shadow-sm focus:border-primary focus:ring-primary"
                />
                <x-input-error :messages="$errors->get('account_code')" class="mt-2" />
            </div>

            <div>
                <label for="name" class="block text-xs font-semibold text-neutral-700">Libellé</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $chartOfAccount->name) }}"
                    required
                    maxlength="150"
                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="account_type" class="block text-xs font-semibold text-neutral-700">Type</label>
                <select id="account_type" name="account_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="asset" @selected(old('account_type', $chartOfAccount->account_type) === 'asset')>Actif</option>
                    <option value="liability" @selected(old('account_type', $chartOfAccount->account_type) === 'liability')>Passif</option>
                    <option value="equity" @selected(old('account_type', $chartOfAccount->account_type) === 'equity')>Capitaux propres</option>
                    <option value="revenue" @selected(old('account_type', $chartOfAccount->account_type) === 'revenue')>Produit</option>
                    <option value="expense" @selected(old('account_type', $chartOfAccount->account_type) === 'expense')>Charge</option>
                </select>
                <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', $chartOfAccount->is_active))
                        class="rounded border-neutral-300 text-primary focus:ring-primary"
                    >
                    <span>Compte actif</span>
                </label>
                <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                <a href="{{ route('chart-of-accounts.index') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                    Annuler
                </a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                    Enregistrer
                </button>
            </div>
        </form>
    </x-caisse-flow>
</x-app-layout>
