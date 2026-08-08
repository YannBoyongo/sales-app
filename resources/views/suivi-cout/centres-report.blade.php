<x-app-layout>
    <x-slot name="header">Rapport par centre de coût</x-slot>

    @php
        $signedUsd = function (string $amount): string {
            $value = (float) $amount;
            $sign = $value >= 0 ? '+' : '-';

            return $sign.' '.\App\Support\Money::usd(abs($value));
        };

        $netColor = bccomp($totals['net'], '0', 2) >= 0 ? 'text-emerald-700' : 'text-red-700';
    @endphp

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Suivi de coût</p>
                    <h1 class="app-page-title">Rapport par centre de coût</h1>
                    <p class="app-page-desc max-w-2xl">
                        Entrées, sorties et solde net pour un centre de coût sur la période choisie.
                    </p>
                </div>
                <a href="{{ route('suivi-cout') }}" class="app-btn-secondary shrink-0 self-start">
                    Retour au suivi
                </a>
            </div>
        </x-slot>

        <form method="GET" action="{{ route('suivi-cout.centres-report') }}" class="app-filter-bar mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <input type="hidden" name="loaded" value="1" />

            <div>
                <label for="cost_center_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Centre de coût</label>
                <select id="cost_center_id" name="cost_center_id" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Choisir…</option>
                    @foreach ($costCenters as $center)
                        <option value="{{ $center->id }}" @selected((string) ($filters['cost_center_id'] ?? '') === (string) $center->id)>
                            {{ $center->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cost_transaction_type_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type de transaction</label>
                <select id="cost_transaction_type_id" name="cost_transaction_type_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Tous</option>
                    @foreach ($transactionTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['cost_transaction_type_id'] ?? '') === (string) $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Du</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>

            <div>
                <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Au</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>

            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-2">
                <button type="submit" class="app-btn-primary">Charger</button>
                <a href="{{ route('suivi-cout.centres-report') }}" class="app-btn-secondary">Réinitialiser</a>
            </div>
        </form>

        @if ($loaded && $selectedCenter)
            <p class="mb-4 text-sm text-neutral-600">
                Période : {{ $periodLabel }}
                · Centre : {{ $selectedCenter->name }}
            </p>
        @endif

        <div class="app-table-shell overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-100 text-xs font-semibold uppercase tracking-wide text-neutral-600">
                            <th colspan="2" class="px-4 py-3 text-left">Type de transaction</th>
                            <th colspan="2" class="px-4 py-3 text-right">Montant</th>
                        </tr>
                    </thead>

                    @if ($loaded && $selectedCenter)
                        <tbody>
                            <tr class="border-b border-neutral-200 bg-primary/5">
                                <td colspan="2" class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a
                                            href="{{ route('suivi-cout.centres-report') }}"
                                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-neutral-200 bg-white text-neutral-500 hover:bg-neutral-50 hover:text-neutral-700"
                                            title="Fermer"
                                            aria-label="Fermer"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        <span class="font-semibold text-neutral-900">
                                            {{ $selectedCenter->name }}
                                            <span class="font-normal text-neutral-600">({{ $totals['entries'] }} {{ $totals['entries'] === 1 ? 'écriture' : 'écritures' }})</span>
                                        </span>
                                    </div>
                                </td>
                                <td colspan="2" class="px-4 py-3 text-right tabular-nums font-semibold {{ $netColor }}">
                                    {{ $signedUsd($totals['net']) }}
                                </td>
                            </tr>

                            @forelse ($entries as $entry)
                                <tr class="border-b border-neutral-100 transition-colors hover:bg-neutral-50/70">
                                    <td class="whitespace-nowrap px-4 py-3 text-neutral-700">
                                        {{ $entry->occurred_on->translatedFormat('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-800">
                                        {{ $entry->description ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        @if ($entry->isEntry())
                                            <span class="font-medium text-emerald-700">{{ \App\Support\Money::usd($entry->amount) }}</span>
                                        @else
                                            <span class="text-red-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        @if (! $entry->isEntry())
                                            <span class="font-medium text-red-700">{{ \App\Support\Money::usd($entry->amount) }}</span>
                                        @else
                                            <span class="text-red-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-12 text-center text-neutral-500">
                                        Aucune écriture pour cette période.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if ($entries->isNotEmpty())
                            <tfoot class="border-t border-neutral-200 bg-white text-sm">
                                <tr class="border-b border-neutral-100">
                                    <td colspan="2" class="px-4 py-3 font-medium text-neutral-800">Total</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">
                                        {{ \App\Support\Money::usd($totals['entry']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-red-700">
                                        {{ \App\Support\Money::usd($totals['exit']) }}
                                    </td>
                                </tr>
                                <tr class="border-b border-neutral-100">
                                    <td colspan="2" class="px-4 py-3 font-medium text-neutral-800">Total général</td>
                                    <td colspan="2" class="px-4 py-3 text-right tabular-nums font-semibold {{ $netColor }}">
                                        {{ $signedUsd($totals['net']) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="px-4 py-4 text-base font-bold text-neutral-900">Total</td>
                                    <td colspan="2" class="px-4 py-4 text-right text-base tabular-nums font-bold {{ $netColor }}">
                                        {{ $signedUsd($totals['net']) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    @else
                        <tbody>
                            <tr>
                                <td colspan="4" class="px-4 py-14 text-center text-neutral-500">
                                    Sélectionnez un centre de coût et cliquez sur Charger.
                                </td>
                            </tr>
                        </tbody>
                    @endif
                </table>
            </div>
        </div>
    </x-caisse-flow>
</x-app-layout>
