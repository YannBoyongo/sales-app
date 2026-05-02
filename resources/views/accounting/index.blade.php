<x-app-layout>
    <x-slot name="header">Comptabilité</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" x-data="{ openModal: {{ $errors->any() ? 'true' : 'false' }} }">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Comptabilité</h1>
            <p class="mt-1 text-sm text-neutral-600">Suivi des écritures et du solde courant.</p>
        </div>
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            @click="openModal = true"
        >
            Passer une ecriture
        </button>

        <div x-show="openModal" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="openModal = false"></div>
            <div class="relative z-10 w-full max-w-xl rounded-xl border border-neutral-200 bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-neutral-900">Nouvelle écriture</h2>
                <form action="{{ route('accounting.store') }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label for="transaction_date" value="Date" />
                        <x-text-input id="transaction_date" name="transaction_date" type="date" class="mt-1 block w-full" :value="old('transaction_date', now()->toDateString())" required />
                        <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Montant" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="reference" value="Référence / Description" />
                        <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full" :value="old('reference')" required />
                        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Type d'écriture" />
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                                <input type="radio" name="entry_type" value="debit" @checked(old('entry_type', 'debit') === 'debit') class="text-primary focus:ring-primary" />
                                <span>Debit</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                                <input type="radio" name="entry_type" value="credit" @checked(old('entry_type') === 'credit') class="text-primary focus:ring-primary" />
                                <span>Credit</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('entry_type')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="openModal = false">Annuler</button>
                        <x-primary-button>Enregistrer</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Total débit</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-700">{{ \App\Support\Money::usd($totalDebit) }}</p>
        </div>
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Total crédit</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-red-700">{{ \App\Support\Money::usd($totalCredit) }}</p>
        </div>
        <div class="rounded-lg border border-primary/25 bg-primary/5 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600">Caisse (débit − crédit)</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($caisse) }}</p>
        </div>
    </div>

    <section class="mb-6 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('accounting.index') }}" class="grid gap-3 sm:grid-cols-[220px_220px_auto_auto] sm:items-end">
            <div>
                <x-input-label for="start_date" value="Date début" />
                <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="$filters['start_date'] ?? ''" />
            </div>
            <div>
                <x-input-label for="end_date" value="Date fin" />
                <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="$filters['end_date'] ?? ''" />
            </div>
            <div class="flex gap-2">
                <x-primary-button>Filtrer</x-primary-button>
                <a href="{{ route('accounting.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Réinitialiser</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto px-6 py-4">
            <table class="min-w-full text-sm">
                <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="py-3 pr-4">Date</th>
                        <th class="py-3 pr-4">Référence / Description</th>
                        <th class="py-3 pr-4 text-right">Debit</th>
                        <th class="py-3 pr-4 text-right">Credit</th>
                        <th class="py-3 text-right">Solde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="py-3 pr-4 whitespace-nowrap text-neutral-700">{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->translatedFormat('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-neutral-900">{{ $row->reference }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums text-emerald-700">{{ (float) $row->debit_amount > 0 ? \App\Support\Money::usd($row->debit_amount) : '—' }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums text-red-700">{{ (float) $row->credit_amount > 0 ? \App\Support\Money::usd($row->credit_amount) : '—' }}</td>
                            <td class="py-3 text-right tabular-nums font-semibold text-primary">{{ \App\Support\Money::usd($row->running_balance) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-neutral-500">Aucune écriture enregistrée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-6 py-3">
            {{ $rows->links() }}
        </div>
    </section>
</x-app-layout>
