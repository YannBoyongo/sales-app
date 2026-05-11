<x-app-layout>
    <x-slot name="header">Bons de caisse</x-slot>

    <div
        x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }"
        @keydown.escape.window="open = false"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Finances</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Bon de caisse</h1>
                        <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                            Suivez toutes les entrées et sorties de caisse avec une référence unique par bon.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="open = true"
                    >
                        Nouveau bon de caisse
                    </button>
                </div>
            </x-slot>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total entrées</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-900">{{ number_format($totalEntries, 2, ',', ' ') }}</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Total sorties</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-red-900">{{ number_format($totalExits, 2, ',', ' ') }}</p>
                </div>
                <div class="rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary">Solde (Entrées - Sorties)</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-neutral-900">{{ number_format($balance, 2, ',', ' ') }}</p>
                </div>
            </div>
            <p class="text-xs text-neutral-500">
                Totaux et solde : <strong class="font-medium text-neutral-700">bons approuvés uniquement</strong> (avec les filtres date et type ci-dessous). La liste inclut tous les statuts.
            </p>

            <form method="GET" action="{{ route('cash-vouchers.index') }}" class="grid gap-3 rounded-2xl border border-neutral-200/90 bg-white/90 p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-1">
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div class="lg:col-span-1">
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div class="lg:col-span-1">
                    <label for="type_filter" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</label>
                    <select id="type_filter" name="type" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Tous</option>
                        <option value="entry" @selected(($filters['type'] ?? '') === 'entry')>Entrée</option>
                        <option value="exit" @selected(($filters['type'] ?? '') === 'exit')>Sortie</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 lg:col-span-1">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">Filtrer</button>
                    <a href="{{ route('cash-vouchers.index') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Réinitialiser</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="px-4 py-3">N° bon</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Montant</th>
                            @if (auth()->user()?->isAdmin())
                                <th class="px-4 py-3 text-right">Action</th>
                            @endif
                            @if (auth()->user()?->canAccessAccounting())
                                <th class="px-4 py-3 text-right">Comptabilité</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($cashVouchers as $voucher)
                            <tr class="transition-colors hover:bg-neutral-50/80">
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-neutral-900">{{ $voucher->voucher_no }}</span>
                                        @if ($voucher->accounting_transaction_id)
                                            <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Comptabilisé</span>
                                        @elseif ($voucher->approved_at)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Approuvé</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">En attente</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-neutral-700">{{ $voucher->date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $voucher->description }}</td>
                                <td class="px-4 py-3">
                                    @if ($voucher->type === 'entry')
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Entrée</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Sortie</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums text-neutral-900">{{ number_format((float) $voucher->amount, 2, ',', ' ') }}</td>
                                @if (auth()->user()?->isAdmin())
                                    <td class="px-4 py-3 text-right">
                                        @if (! $voucher->approved_at)
                                            <form action="{{ route('cash-vouchers.approve', $voucher) }}" method="POST" onsubmit="return confirm('Approuver ce bon de caisse ?');" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    title="Approuver"
                                                    aria-label="Approuver"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-neutral-500">—</span>
                                        @endif
                                    </td>
                                @endif
                                @if (auth()->user()?->canAccessAccounting())
                                    <td class="px-4 py-3 text-right">
                                        @if ($voucher->approved_at && ! $voucher->accounting_transaction_id)
                                            <a href="{{ route('cash-vouchers.accounting.create', $voucher) }}" class="inline-flex items-center rounded-lg border border-primary/25 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/15">
                                                Imputer
                                            </a>
                                        @else
                                            <span class="text-xs text-neutral-500">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()?->isAdmin() ? (auth()->user()?->canAccessAccounting() ? 7 : 6) : (auth()->user()?->canAccessAccounting() ? 6 : 5) }}" class="px-4 py-10 text-center text-neutral-500">Aucun bon de caisse.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $cashVouchers->links() }}</div>
        </x-caisse-flow>

        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-transition.opacity
        >
            <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="cash-voucher-title"
                class="relative z-10 w-full max-w-xl rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5"
                @click.stop
            >
                <h2 id="cash-voucher-title" class="text-lg font-semibold text-neutral-900">Nouveau bon de caisse</h2>
                <p class="mt-1 text-sm text-neutral-600">Renseignez les détails du bon de caisse.</p>

                <form action="{{ route('cash-vouchers.store') }}" method="POST" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="voucher_no" class="block text-xs font-semibold text-neutral-700">Numéro du bon</label>
                        <input
                            id="voucher_no"
                            name="voucher_no"
                            type="text"
                            value="{{ old('voucher_no') }}"
                            required
                            maxlength="100"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Ex: BC-2026-001"
                        />
                        <x-input-error :messages="$errors->get('voucher_no')" class="mt-2" />
                    </div>

                    <div>
                        <label for="date" class="block text-xs font-semibold text-neutral-700">Date</label>
                        <input
                            id="date"
                            name="date"
                            type="date"
                            value="{{ old('date', now()->toDateString()) }}"
                            required
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                        />
                        <x-input-error :messages="$errors->get('date')" class="mt-2" />
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-semibold text-neutral-700">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            required
                            maxlength="2000"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Motif du bon de caisse"
                        >{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <p class="block text-xs font-semibold text-neutral-700">Type</p>
                        <div class="mt-2 flex flex-wrap items-center gap-5">
                            <label class="inline-flex items-center gap-2 text-sm text-neutral-800">
                                <input type="radio" name="type" value="entry" @checked(old('type', 'entry') === 'entry') class="border-neutral-300 text-primary focus:ring-primary" required>
                                <span>Entrée</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-neutral-800">
                                <input type="radio" name="type" value="exit" @checked(old('type') === 'exit') class="border-neutral-300 text-primary focus:ring-primary" required>
                                <span>Sortie</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div>
                        <label for="amount" class="block text-xs font-semibold text-neutral-700">Montant</label>
                        <input
                            id="amount"
                            name="amount"
                            type="number"
                            value="{{ old('amount') }}"
                            required
                            min="0.01"
                            step="0.01"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="0.00"
                        />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="open = false">
                            Annuler
                        </button>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
