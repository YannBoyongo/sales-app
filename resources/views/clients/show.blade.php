<x-app-layout>
    <x-slot name="header">Client — {{ $client->name }}</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ $client->name }}</h1>
            @if ($client->phone)
                <p class="app-page-desc">{{ $client->phone }}</p>
            @endif
            <p class="app-page-desc">
                @if ($showFinanceDetail)
                    Suivi des ventes à crédit et paiements par échéances.
                @else
                    Fiche cliente : vos droits permettent de créer ou mettre à jour les coordonnées. Le suivi des dettes reste réservé à la caisse ou à la comptabilité.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if (auth()->user()?->canEditClientProfile())
                <a href="{{ route('clients.edit', $client) }}" class="app-btn-secondary">
                    Modifier
                </a>
            @endif
            @if ($showFinanceDetail)
                <a href="{{ route('sales.overview') }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Liste des ventes</a>
            @endif
            <a href="{{ route('clients.index') }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Clients</a>
        </div>
    </div>

    @if ($showFinanceDetail)
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="app-stat-card">
                <p class="text-xs uppercase tracking-wide text-neutral-500">Total crédit</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($totalCredit) }}</p>
            </div>
            <div class="app-stat-card">
                <p class="text-xs uppercase tracking-wide text-neutral-500">Total payé</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($totalPaid) }}</p>
            </div>
            <div class="app-stat-card">
                <p class="text-xs uppercase tracking-wide text-neutral-500">Dette restante</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($balance) }}</p>
            </div>
        </div>

        <section class="app-panel app-panel-body mb-6 max-w-xl">
            <h2 class="text-lg font-semibold text-neutral-900">Enregistrer un paiement (échéance)</h2>
            <form action="{{ route('clients.payments.store', $client) }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <div>
                    <x-input-label for="amount" value="Montant payé" />
                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                </div>
                <div>
                    <x-input-label for="note" value="Note (optionnel)" />
                    <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" />
                    <x-input-error class="mt-2" :messages="$errors->get('note')" />
                </div>
                <div class="flex justify-end">
                    <x-primary-button>Enregistrer le paiement</x-primary-button>
                </div>
            </form>
        </section>

        <section class="app-panel mb-6">
            <div class="app-panel-header">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Ventes à crédit</h2>
                    <p class="mt-1 text-sm text-neutral-600">Montants « à payer », « payé » et « solde » correspondent au total de la vente (facture), pas à la ligne seule.</p>
                </div>
            </div>
            <div class="app-table-shell border-0 shadow-none">
                @php
                    $creditLines = $client->creditSales;
                    $linesPerSale = $creditLines->groupBy(fn ($line) => $line->sale_id ?? 'orphan-'.$line->id)->map->count();
                    $saleTotalsRendered = [];
                @endphp
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Vente</th>
                            <th class="py-3 pr-4">Produit</th>
                            <th class="py-3 pr-4 text-right">Qté</th>
                            <th class="py-3 pr-4 text-right">À payer</th>
                            <th class="py-3 pr-4 text-right">Total payé</th>
                            <th class="py-3 text-right">Solde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($creditLines as $line)
                            @php
                                $saleGroupKey = $line->sale_id ?? 'orphan-'.$line->id;
                                $parentSale = $line->sale;
                                $showSaleTotals = ! isset($saleTotalsRendered[$saleGroupKey]);
                                if ($showSaleTotals) {
                                    $saleTotalsRendered[$saleGroupKey] = true;
                                }
                                $saleRowSpan = $linesPerSale[$saleGroupKey] ?? 1;
                                $expectedAmount = $parentSale?->expectedPayableAmount();
                                $paidAmount = $parentSale?->paidAmountValue();
                                $remainingAmount = $parentSale?->remainingAmountValue();
                            @endphp
                            <tr>
                                <td class="py-3 pr-4 text-neutral-600">{{ $line->created_at->translatedFormat('d/m/Y H:i') }}</td>
                                <td class="py-3 pr-4">
                                    @if ($parentSale)
                                        <a class="font-medium text-primary hover:underline" href="{{ route('sales.show', [$line->branch, $parentSale]) }}">{{ $parentSale->reference }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-neutral-700">{{ $line->product->name }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ $line->quantity }}</td>
                                @if ($showSaleTotals)
                                    <td rowspan="{{ $saleRowSpan }}" class="border-l border-neutral-100 bg-neutral-50/60 py-3 pr-4 text-right align-top tabular-nums font-medium text-neutral-900">
                                        @if ($parentSale)
                                            {{ \App\Support\Money::usd($expectedAmount) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td rowspan="{{ $saleRowSpan }}" class="bg-neutral-50/60 py-3 pr-4 text-right align-top tabular-nums text-neutral-800">
                                        @if ($parentSale)
                                            {{ \App\Support\Money::usd($paidAmount) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td rowspan="{{ $saleRowSpan }}" class="bg-neutral-50/60 py-3 text-right align-top tabular-nums font-semibold">
                                        @if ($parentSale)
                                            <span class="{{ (float) $remainingAmount > 0 ? 'text-amber-800' : 'text-emerald-700' }}">
                                                {{ \App\Support\Money::usd($remainingAmount) }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-neutral-500">Aucune vente à crédit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="app-panel mb-6 border-sky-200">
            <div class="app-panel-header border-sky-100 bg-sky-50">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Caution</h2>
                    <p class="mt-1 text-sm text-neutral-600">Portefeuille de dépôt : le client peut verser une caution au fil du temps.</p>
                </div>
            </div>
            <div class="app-panel-body">
                <div class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-sky-200 bg-sky-50/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-sky-800/80">Total caution</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-sky-900">{{ \App\Support\Money::usd($cautionTotal) }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-neutral-500">Montant utilisé</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($cautionUsed) }}</p>
                    </div>
                    <div class="rounded-lg border border-sky-200 bg-sky-50/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-sky-800/80">Solde</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-sky-900">{{ \App\Support\Money::usd($cautionBalance) }}</p>
                    </div>
                </div>

                <div class="mb-8 max-w-xl">
                    <h3 class="text-sm font-semibold text-neutral-900">Enregistrer un dépôt</h3>
                    <form action="{{ route('clients.caution-deposits.store', $client) }}" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="caution_amount" value="Montant du dépôt" />
                            <x-text-input id="caution_amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                        </div>
                        <div>
                            <x-input-label for="caution_note" value="Note (optionnel)" />
                            <x-text-input id="caution_note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" />
                            <x-input-error class="mt-2" :messages="$errors->get('note')" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Enregistrer le dépôt</x-primary-button>
                        </div>
                    </form>
                </div>

                <div class="app-table-shell border-0 shadow-none">
                    <h3 class="mb-3 px-4 pt-4 text-sm font-semibold text-neutral-900">Historique des dépôts</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                            <tr>
                                <th class="py-3 pr-4">Date</th>
                                <th class="py-3 pr-4">Enregistré par</th>
                                <th class="py-3 pr-4">Note</th>
                                <th class="py-3 pr-4 text-right">Montant</th>
                                @if (auth()->user()?->isAdmin())
                                    <th class="py-3 text-right">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($client->cautionDeposits as $deposit)
                                <tr>
                                    <td class="py-3 pr-4 text-neutral-600">{{ $deposit->deposited_at->translatedFormat('d/m/Y H:i') }}</td>
                                    <td class="py-3 pr-4 text-neutral-700">{{ $deposit->user?->name ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-neutral-700">{{ $deposit->note ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-right tabular-nums font-medium text-sky-900">{{ \App\Support\Money::usd($deposit->amount) }}</td>
                                    @if (auth()->user()?->isAdmin())
                                        <td class="py-3 text-right">
                                            <form
                                                action="{{ route('clients.caution-deposits.destroy', [$client, $deposit]) }}"
                                                method="POST"
                                                onsubmit="return confirm('Supprimer ce dépôt de caution ? Le bon de caisse associé sera aussi retiré s’il n’a pas été comptabilisé.');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    title="Supprimer"
                                                    aria-label="Supprimer"
                                                    class="app-icon-btn-danger"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7.5h12m-9.75 0V6a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v1.5m-8.25 0v10.5A1.5 1.5 0 009 19.5h6a1.5 1.5 0 001.5-1.5V7.5M10.5 10.5v6m3-6v6" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()?->isAdmin() ? 5 : 4 }}" class="py-8 text-center text-neutral-500">Aucun dépôt de caution enregistré.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="app-table-shell mt-8 border-0 shadow-none">
                    <h3 class="mb-3 px-4 pt-4 text-sm font-semibold text-neutral-900">Utilisations de caution</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                            <tr>
                                <th class="py-3 pr-4">Date</th>
                                <th class="py-3 pr-4">Vente</th>
                                <th class="py-3 pr-4">Enregistré par</th>
                                <th class="py-3 pr-4">Note</th>
                                <th class="py-3 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($client->cautionUsages as $usage)
                                <tr>
                                    <td class="py-3 pr-4 text-neutral-600">{{ $usage->used_at->translatedFormat('d/m/Y H:i') }}</td>
                                    <td class="py-3 pr-4">
                                        @if ($usage->sale && $usage->sale->branch)
                                            <a class="font-medium text-primary hover:underline" href="{{ route('sales.show', [$usage->sale->branch, $usage->sale]) }}">{{ $usage->sale->reference }}</a>
                                        @elseif ($usage->sale)
                                            <span class="font-medium text-neutral-800">{{ $usage->sale->reference }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-neutral-700">{{ $usage->user?->name ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-neutral-700">{{ $usage->note ?? '—' }}</td>
                                    <td class="py-3 text-right tabular-nums font-medium text-amber-800">{{ \App\Support\Money::usd($usage->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-neutral-500">Aucune utilisation de caution enregistrée.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="app-panel">
            <div class="app-panel-header">
                <h2 class="text-lg font-semibold text-neutral-900">Paiements</h2>
            </div>
            <div class="app-table-shell border-0 shadow-none">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Enregistré par</th>
                            <th class="py-3 pr-4">Note</th>
                            <th class="py-3 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($client->payments as $payment)
                            <tr>
                                <td class="py-3 pr-4 text-neutral-600">{{ $payment->paid_at->translatedFormat('d/m/Y H:i') }}</td>
                                <td class="py-3 pr-4 text-neutral-700">{{ $payment->user?->name ?? '—' }}</td>
                                <td class="py-3 pr-4 text-neutral-700">{{ $payment->note ?? '—' }}</td>
                                <td class="py-3 text-right tabular-nums">{{ \App\Support\Money::usd($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-neutral-500">Aucun paiement enregistré.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-app-layout>
