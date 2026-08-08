<x-app-layout>
    <x-slot name="header">Ventes - {{ $posTerminal->name }}</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Caisse</p>
                    <h1 class="app-page-title">Ventes sur {{ $posTerminal->name }}</h1>
                    <p class="app-page-desc max-w-3xl">
                        Historique de toutes les ventes enregistrées sur ce terminal, toutes sessions confondues.
                    </p>
                    <p class="mt-3 inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-full border border-neutral-200/80 bg-white/80 px-4 py-1.5 text-sm text-neutral-700 shadow-sm backdrop-blur-sm">
                        <span class="text-neutral-500">Branche</span>
                        <strong class="text-neutral-900">{{ $branch->name }}</strong>
                        <span class="text-neutral-300">·</span>
                        <span class="text-neutral-500">Stock</span>
                        <strong class="text-neutral-900">{{ $posTerminal->location?->name ?? '-' }}</strong>
                    </p>
                </div>
                <a
                    href="{{ route($routes['workspace'], [$branch, $posTerminal]) }}"
                    class="app-btn-secondary shrink-0 gap-2"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Retour au terminal
                </a>
            </div>
        </x-slot>

        <div class="app-filter-bar mb-4">
            <form method="GET" action="{{ route($routes['sales_index'], [$branch, $posTerminal]) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Vente du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="app-input mt-1" />
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Vente au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="app-input mt-1" />
                </div>
                <div>
                    <label for="payment_type" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type de paiement</label>
                    <select id="payment_type" name="payment_type" class="app-input mt-1">
                        <option value="">Tous</option>
                        <option value="cash" @selected(($filters['payment_type'] ?? '') === 'cash')>Cash</option>
                        <option value="credit" @selected(($filters['payment_type'] ?? '') === 'credit')>Crédit</option>
                        <option value="caution" @selected(($filters['payment_type'] ?? '') === 'caution')>Caution</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="app-btn-primary">Filtrer</button>
                    <a href="{{ route($routes['sales_index'], [$branch, $posTerminal]) }}" class="app-btn-secondary">Réinitialiser</a>
                </div>
            </form>
            @if ($errors->has('date_from') || $errors->has('date_to'))
                <p class="mt-3 text-sm text-red-700">{{ $errors->first('date_from') ?: $errors->first('date_to') }}</p>
            @endif
        </div>

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                        <th class="px-4 py-3 whitespace-nowrap">Référence</th>
                        @if ($posTerminal->isFieldPointOfSale())
                            <th class="px-4 py-3 whitespace-nowrap">Vendu à</th>
                        @endif
                        <th class="px-4 py-3 whitespace-nowrap">Session</th>
                        <th class="px-4 py-3 whitespace-nowrap">Caissier</th>
                        <th class="px-4 py-3 whitespace-nowrap">Type</th>
                        <th class="px-4 py-3 whitespace-nowrap">Statut paiement</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">A payer</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Payé</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Reste</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 bg-white">
                    @forelse ($sales as $sale)
                        @php($shift = $sale->posShift)
                        <tr @class([
                            'transition-colors hover:bg-neutral-50/80' => ! $sale->isPendingDiscount(),
                            'bg-amber-50/90 hover:bg-amber-100/80' => $sale->isPendingDiscount(),
                        ])>
                            <td class="px-4 py-3.5 text-neutral-600 whitespace-nowrap">{{ $sale->effectiveSoldAt()->translatedFormat('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 font-mono text-sm text-neutral-800">{{ $sale->reference }}</td>
                            @if ($posTerminal->isFieldPointOfSale())
                                <td class="px-4 py-3.5 text-neutral-700">{{ $sale->saleLocation?->name ?? '-' }}</td>
                            @endif
                            <td class="px-4 py-3.5 text-neutral-700">
                                @if ($shift)
                                    <div class="leading-snug">
                                        @if ($canViewClosedShiftDetail && $shift->closed_at !== null)
                                            <a href="{{ route('pos-terminal.shifts.closed.show', $shift) }}" class="font-medium text-primary hover:underline">
                                                {{ $shift->effectiveSessionDate()->translatedFormat('d/m/Y') }}
                                            </a>
                                        @else
                                            <span class="font-medium text-neutral-900">{{ $shift->effectiveSessionDate()->translatedFormat('d/m/Y') }}</span>
                                        @endif
                                        @if ($shift->closed_at === null)
                                            <span class="mt-0.5 block text-xs font-medium text-emerald-700">Session ouverte</span>
                                        @else
                                            <span class="mt-0.5 block text-xs text-neutral-500">Session fermée</span>
                                        @endif
                                        @if ($shift->openedByUser)
                                            <span class="mt-0.5 block text-xs text-neutral-500">Ouverte par {{ $shift->openedByUser->name }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-neutral-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-neutral-700">{{ $sale->user?->name ?? '-' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @if ($sale->payment_type === 'credit')
                                    <span class="app-badge-warning">Crédit</span>
                                @elseif ($sale->payment_type === 'caution')
                                    <span class="app-badge-info">Caution</span>
                                @else
                                    <span class="app-badge-success">Cash</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @php($effectiveStatus = $sale->effectivePaymentStatus())
                                @if ($sale->isPendingDiscount())
                                    <span class="app-badge-warning">Remise en attente</span>
                                @elseif ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_NOT_PAID)
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-900">Non payé</span>
                                @elseif ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_PARTIALLY_PAID)
                                    <span class="app-badge-warning">Partiellement payé</span>
                                @else
                                    <span class="app-badge-success">Entièrement payé</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums font-semibold text-neutral-900">
                                {{ \App\Support\Money::usd($sale->expectedPayableAmount()) }}
                            </td>
                            <td class="px-4 py-3.5 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($sale->paidAmountValue()) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums font-medium text-amber-800">{{ \App\Support\Money::usd($sale->remainingAmountValue()) }}</td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('sales.show', [$branch, $sale]) }}"
                                    class="app-icon-btn"
                                    title="Voir la vente"
                                >
                                    <span class="sr-only">Voir</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-14 text-center text-neutral-500">
                                @if (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) || ($filters['payment_type'] ?? null))
                                    Aucune vente pour ces critères.
                                @else
                                    Aucune vente enregistrée sur ce terminal.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sales->hasPages())
            <div class="mt-4">
                {{ $sales->links() }}
            </div>
        @endif
    </x-caisse-flow>
</x-app-layout>
