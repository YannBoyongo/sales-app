<x-app-layout>
    <x-slot name="header">Session #{{ $salesSession->id }}</x-slot>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Session journalière #{{ $salesSession->id }}</h1>
            <p class="mt-1 text-sm text-neutral-600">
                {{ $salesSession->branch->name }}
                · Ouverte le {{ $salesSession->opened_at->translatedFormat('d/m/Y à H:i') }}
                @if ($salesSession->opener)
                    par {{ $salesSession->opener->name }}
                @endif
            </p>
            @if ($salesSession->isOpen())
                <span class="mt-2 inline-block rounded bg-primary px-2 py-0.5 text-xs font-medium text-white">Ouverte</span>
            @else
                <span class="mt-2 inline-block rounded bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800">Clôturée</span>
                @if ($salesSession->closed_at)
                    <p class="mt-2 text-sm text-neutral-600">Clôturée le {{ $salesSession->closed_at->translatedFormat('d/m/Y à H:i') }}</p>
                @endif
                @if ($salesSession->closure_total_amount !== null)
                    <p class="text-sm text-neutral-800 mt-1">Montant déclaré : <span class="font-medium tabular-nums">{{ \App\Support\Money::usd($salesSession->closure_total_amount ?? 0) }}</span></p>
                @endif
                @if ($salesSession->closure_bank_reference)
                    <p class="text-sm text-neutral-600">Justificatif bancaire : {{ $salesSession->closure_bank_reference }}</p>
                @endif
            @endif
        </div>
        <div class="flex flex-col items-end gap-2">
            @if (! $salesSession->isOpen())
                <a href="{{ route('sales-sessions.closure-recap', $salesSession) }}" class="text-sm font-medium text-primary underline-offset-2 hover:underline">Récapitulatif de clôture</a>
            @endif
            <a href="{{ route('sales-sessions.index') }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Retour à la liste</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif

    @if ($errors->has('close'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('close') }}</div>
    @endif
    @if ($errors->has('reopen'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('reopen') }}</div>
    @endif
    @if ($errors->has('delete'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('delete') }}</div>
    @endif
    @if ($errors->has('sale'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('sale') }}</div>
    @endif
    @if ($errors->has('label') || $errors->has('amount') || $errors->has('spent_at'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">Impossible d’enregistrer la dépense. Vérifiez les champs du formulaire.</div>
    @endif

    @if (auth()->user()->is_admin)
        <div class="mb-6 rounded-lg border border-neutral-200 bg-neutral-50 p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-neutral-900">Administration</h2>
            @if (! $salesSession->isOpen())
                <p class="mt-1 text-sm text-neutral-700">Session clôturée : vous pouvez la rouvrir pour permettre à nouveau les ventes et une nouvelle clôture (les lignes de vente existantes sont conservées).</p>
                <form action="{{ route('sales-sessions.reopen', $salesSession) }}" method="POST" class="mt-3">
                    @csrf
                    <x-secondary-button type="submit" onclick="return confirm('Rouvrir cette session ? Les informations de clôture précédentes seront effacées.');">Rouvrir la session</x-secondary-button>
                </form>
            @endif
        </div>
    @endif

    @if ($salesSession->isOpen() && $salesSession->hasPendingDiscountSales())
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            {{ $salesSession->pendingDiscountSalesCount() }} vente(s) avec remise en attente d’approbation. La clôture de session est bloquée jusqu’à ce qu’un administrateur approuve ou refuse chaque remise.
        </div>
    @endif

    @if ($salesSession->isOpen())
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-3">
                <a
                    href="{{ route('sale-items.create', $salesSession) }}"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    Nouvelle vente
                </a>
                <a
                    href="{{ route('sales-sessions.closure', $salesSession) }}"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                >
                    Clôturer vente
                </a>
            </div>
            <p class="text-sm text-neutral-600">
                Cash : <span class="font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($cashTotal) }}</span>
                · Dépenses : <span class="font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($expensesTotal) }}</span>
                · Net : <span class="font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($netTotal) }}</span>
                · Crédit (info) : <span class="font-semibold tabular-nums text-neutral-700">{{ \App\Support\Money::usd($creditTotal) }}</span>
            </p>
        </div>
    @endif

    <section class="mb-6 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
            <h2 class="text-lg font-semibold text-neutral-900">Dépenses de la session</h2>
        </div>
        <div class="space-y-5 px-6 py-4">
            @if ($salesSession->isOpen())
                <form action="{{ route('sales-sessions.expenses.store', $salesSession) }}" method="POST" class="grid gap-3 md:grid-cols-[1fr_180px_220px_auto]">
                    @csrf
                    <div>
                        <x-input-label for="label" value="Libellé" />
                        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label')" required placeholder="Ex. Transport, eau, électricité..." />
                        <x-input-error :messages="$errors->get('label')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Montant" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="spent_at" value="Date/heure" />
                        <x-text-input id="spent_at" name="spent_at" type="datetime-local" class="mt-1 block w-full" :value="old('spent_at')" />
                        <x-input-error :messages="$errors->get('spent_at')" class="mt-2" />
                    </div>
                    <div class="flex items-end">
                        <x-primary-button>Ajouter dépense</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Libellé</th>
                            <th class="py-3 pr-4">Saisi par</th>
                            <th class="py-3 pr-4 text-right">Montant</th>
                            @if ($salesSession->isOpen())
                                <th class="py-3 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($salesSession->expenses as $expense)
                            <tr>
                                <td class="py-3 pr-4 whitespace-nowrap text-neutral-600">{{ $expense->spent_at?->translatedFormat('d/m H:i') ?? '—' }}</td>
                                <td class="py-3 pr-4 font-medium text-neutral-900">{{ $expense->label }}</td>
                                <td class="py-3 pr-4 text-neutral-700">{{ $expense->user?->name ?? '—' }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($expense->amount) }}</td>
                                @if ($salesSession->isOpen())
                                    <td class="py-3 text-right">
                                        <form action="{{ route('sales-sessions.expenses.destroy', [$salesSession, $expense]) }}" method="POST" onsubmit="return confirm('Supprimer cette dépense ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Supprimer</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $salesSession->isOpen() ? 5 : 4 }}" class="py-6 text-center text-neutral-500">Aucune dépense enregistrée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
            <h2 class="text-lg font-semibold text-neutral-900">Ventes</h2>
        </div>
        <div class="overflow-x-auto px-6 py-4">
            <table class="min-w-full text-sm">
                <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="py-3 pr-4">Réf.</th>
                        <th class="py-3 pr-4">Date</th>
                        <th class="py-3 pr-4">Paiement</th>
                        <th class="py-3 pr-4">Client</th>
                        <th class="py-3 pr-4">Statut</th>
                        <th class="py-3 text-right">Montant</th>
                        <th class="py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="py-3 pr-4 font-mono text-xs text-neutral-700">{{ $sale->reference }}</td>
                            <td class="py-3 pr-4 text-neutral-600 whitespace-nowrap">{{ $sale->sold_at->translatedFormat('d/m H:i') }}</td>
                            <td class="py-3 pr-4">
                                @if ($sale->payment_type === 'credit')
                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Crédit</span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Cash</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-neutral-700">{{ $sale->client?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                @if (($sale->sale_status ?? \App\Models\Sale::STATUS_CONFIRMED) === \App\Models\Sale::STATUS_PENDING_DISCOUNT)
                                    <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">Remise en attente</span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-2.5 py-1 text-xs font-semibold text-neutral-700">Confirmée</span>
                                @endif
                            </td>
                            <td class="py-3 text-right tabular-nums">{{ \App\Support\Money::usd($sale->total_amount) }}</td>
                            <td class="py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a
                                        href="{{ route('sales.show', [$salesSession, $sale]) }}"
                                        class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                                    >
                                        Voir
                                    </a>
                                    @if (auth()->user()->is_admin && ($sale->sale_status ?? '') === \App\Models\Sale::STATUS_PENDING_DISCOUNT)
                                        <form action="{{ route('sales.approve-discount', [$salesSession, $sale]) }}" method="POST" class="inline" onsubmit="return confirm('Approuver cette remise et appliquer le nouveau total ?');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-md border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approuver remise</button>
                                        </form>
                                        <form action="{{ route('sales.reject-discount', [$salesSession, $sale]) }}" method="POST" class="inline" onsubmit="return confirm('Refuser la remise ? La vente sera confirmée au sous-total catalogue.');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Refuser</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-neutral-500">Aucune vente enregistrée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-6 py-3">{{ $sales->links() }}</div>
    </section>
</x-app-layout>
