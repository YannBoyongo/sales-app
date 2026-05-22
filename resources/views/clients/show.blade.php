<x-app-layout>
    <x-slot name="header">Client — {{ $client->name }}</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">{{ $client->name }}</h1>
            @if ($client->phone)
                <p class="mt-1 text-sm text-neutral-600">{{ $client->phone }}</p>
            @endif
            <p class="mt-2 text-sm text-neutral-600">
                @if ($showFinanceDetail)
                    Suivi des ventes à crédit et paiements par échéances.
                @else
                    Fiche cliente : vos droits permettent de créer ou mettre à jour les coordonnées. Le suivi des dettes reste réservé à la caisse ou à la comptabilité.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if (auth()->user()?->canEditClientProfile())
                <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center justify-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-800 shadow-sm hover:bg-neutral-50">
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
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-neutral-500">Total crédit</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($totalCredit) }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-neutral-500">Total payé</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($totalPaid) }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-neutral-500">Dette restante</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($balance) }}</p>
            </div>
        </div>

        <section class="mb-6 max-w-xl rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
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

        <section class="mb-6 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
                <h2 class="text-lg font-semibold text-neutral-900">Ventes à crédit</h2>
                <p class="mt-1 text-sm text-neutral-600">Montants « à payer », « payé » et « solde » correspondent au total de la vente (facture), pas à la ligne seule.</p>
            </div>
            <div class="overflow-x-auto px-6 py-4">
                @php
                    $creditLines = $client->creditSales;
                    $linesPerSale = $creditLines->groupBy(fn ($line) => $line->sale_id ?? 'orphan-'.$line->id)->map->count();
                    $saleTotalsRendered = [];
                @endphp
                <table class="min-w-full text-sm">
                    <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
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

        <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
                <h2 class="text-lg font-semibold text-neutral-900">Paiements</h2>
            </div>
            <div class="overflow-x-auto px-6 py-4">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
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
