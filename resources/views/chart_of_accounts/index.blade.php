<x-app-layout>
    <x-slot name="header">Plan comptable</x-slot>

    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" @keydown.escape.window="open = false">
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Finances</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Plan comptable</h1>
                        <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                            Gérez la liste des comptes comptables utilisés par votre organisation.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="open = true"
                    >
                        Nouveau compte
                    </button>
                </div>
            </x-slot>

            <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Libellé</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($accounts as $account)
                            <tr class="transition-colors hover:bg-neutral-50/80">
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-neutral-900">{{ $account->account_code }}</td>
                                <td class="px-4 py-3 text-neutral-800">{{ $account->name }}</td>
                                <td class="px-4 py-3 text-neutral-700">
                                    @php
                                        $labels = [
                                            'asset' => 'Actif',
                                            'liability' => 'Passif',
                                            'equity' => 'Capitaux propres',
                                            'revenue' => 'Produit',
                                            'expense' => 'Charge',
                                        ];
                                    @endphp
                                    {{ $labels[$account->account_type] ?? $account->account_type }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($account->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Actif</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">Inactif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-neutral-500">Aucun compte comptable.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $accounts->links() }}</div>
        </x-caisse-flow>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
            <div role="dialog" aria-modal="true" aria-labelledby="coa-title" class="relative z-10 w-full max-w-xl rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5" @click.stop>
                <h2 id="coa-title" class="text-lg font-semibold text-neutral-900">Nouveau compte comptable</h2>
                <form action="{{ route('chart-of-accounts.store') }}" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="account_code" class="block text-xs font-semibold text-neutral-700">Code du compte</label>
                        <input id="account_code" name="account_code" type="text" value="{{ old('account_code') }}" required maxlength="30" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" placeholder="Ex: 101" />
                        <x-input-error :messages="$errors->get('account_code')" class="mt-2" />
                    </div>
                    <div>
                        <label for="name" class="block text-xs font-semibold text-neutral-700">Libellé</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="150" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" placeholder="Ex: Caisse principale" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="account_type" class="block text-xs font-semibold text-neutral-700">Type</label>
                        <select id="account_type" name="account_type" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                            <option value="">— Choisir —</option>
                            <option value="asset" @selected(old('account_type') === 'asset')>Actif</option>
                            <option value="liability" @selected(old('account_type') === 'liability')>Passif</option>
                            <option value="equity" @selected(old('account_type') === 'equity')>Capitaux propres</option>
                            <option value="revenue" @selected(old('account_type') === 'revenue')>Produit</option>
                            <option value="expense" @selected(old('account_type') === 'expense')>Charge</option>
                        </select>
                        <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1') class="rounded border-neutral-300 text-primary focus:ring-primary">
                            <span>Compte actif</span>
                        </label>
                        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="open = false">Annuler</button>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
