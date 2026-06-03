<x-app-layout>
    <x-slot name="header">Toutes les ventes</x-slot>

    <x-page-header title="Toutes les ventes" :action="auth()->user()?->canAccessPosSales() ? 'Nouvelle vente' : null" :action-href="auth()->user()?->canAccessPosSales() ? route('sales.entry') : null" />

    @if ($errors->has('sale'))
        <div class="app-alert-danger" role="alert">{{ $errors->first('sale') }}</div>
    @endif

    @if ($canApproveDiscounts && $pendingDiscountCount > 0)
        <div class="app-alert-warning flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" role="status">
            <p>
                <span class="font-semibold">{{ $pendingDiscountCount }} remise{{ $pendingDiscountCount > 1 ? 's' : '' }}</span>
                en attente d’approbation administrateur.
            </p>
            <div class="flex flex-wrap gap-2">
                @if (request()->boolean('remise'))
                    <a href="{{ route('sales.overview', request()->only(['date_from', 'date_to', 'pos_terminal_id', 'payment_type'])) }}" class="inline-flex items-center rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100">Toutes les ventes</a>
                @else
                    <a href="{{ route('sales.overview', array_merge(request()->only(['date_from', 'date_to', 'pos_terminal_id', 'payment_type']), ['remise' => 1])) }}" class="inline-flex items-center rounded-md border border-primary/40 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/15">Voir les remises en attente</a>
                @endif
            </div>
        </div>
    @endif

    <form method="GET" action="{{ route('sales.overview') }}" class="app-filter-bar grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @if (request()->boolean('remise'))
            <input type="hidden" name="remise" value="1" />
        @endif
        <div>
            <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
            <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
        </div>
        <div>
            <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
            <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
        </div>
        <div>
            <label for="pos_terminal_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Point de vente</label>
            <select id="pos_terminal_id" name="pos_terminal_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                <option value="">Tous</option>
                @foreach ($posTerminals as $terminal)
                    <option value="{{ $terminal->id }}" @selected((string) ($filters['pos_terminal_id'] ?? '') === (string) $terminal->id)>
                        @if ($showsMultipleBranches)
                            {{ $terminal->branch->name }} — {{ $terminal->name }}
                        @else
                            {{ $terminal->name }}
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_type" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Paiement</label>
            <select id="payment_type" name="payment_type" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                <option value="">Tous</option>
                <option value="cash" @selected(($filters['payment_type'] ?? '') === 'cash')>Cash</option>
                <option value="credit" @selected(($filters['payment_type'] ?? '') === 'credit')>Crédit</option>
                <option value="caution" @selected(($filters['payment_type'] ?? '') === 'caution')>Caution</option>
            </select>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
            <button type="submit" class="app-btn-primary">Filtrer</button>
            <a href="{{ route('sales.overview', request()->boolean('remise') ? ['remise' => 1] : []) }}" class="app-btn-secondary">Réinitialiser</a>
        </div>
    </form>
    @if ($errors->has('date_from') || $errors->has('date_to'))
        <div class="app-alert-danger" role="alert">
            {{ $errors->first('date_from') ?: $errors->first('date_to') }}
        </div>
    @endif

    <div class="app-table-shell">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 whitespace-nowrap">Réf.</th>
                    <th class="px-4 py-3 whitespace-nowrap">Terminal POS</th>
                    <th class="px-4 py-3 whitespace-nowrap">Client</th>
                    <th class="px-4 py-3 whitespace-nowrap">Paiement</th>
                    <th class="px-4 py-3 min-w-[12rem]">Articles</th>
                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">Payé</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">Solde</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($sales as $sale)
                    @php($terminal = $sale->posShift?->posTerminal)
                    <tr @class([
                        'hover:bg-neutral-50/80' => ! $sale->isPendingDiscount(),
                        'bg-amber-50/90 hover:bg-amber-100/80' => $sale->isPendingDiscount(),
                    ])>
                        <td class="px-4 py-3 font-mono text-neutral-800 whitespace-nowrap">{{ $sale->reference }}</td>
                        <td class="px-4 py-3 text-neutral-700">
                            @if ($terminal?->name)
                                {{ $terminal->name }}
                                @if ($showsMultipleBranches && $terminal->branch?->name)
                                    <span class="block text-xs text-neutral-500">{{ $terminal->branch->name }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-700">{{ $sale->displayClientName() ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($sale->payment_type === 'credit')
                                <span class="app-badge-warning">Crédit</span>
                            @elseif ($sale->payment_type === 'caution')
                                <span class="app-badge-info">Caution</span>
                            @else
                                <span class="app-badge-success">Cash</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-700">
                            @if ($sale->items->isEmpty())
                                <span class="text-neutral-400">—</span>
                            @else
                                <ul class="space-y-0.5 text-xs leading-relaxed">
                                    @foreach ($sale->items as $item)
                                        <li>
                                            <span class="font-medium text-neutral-900">{{ $item->product->name }}</span>
                                            <span class="tabular-nums text-neutral-600">× {{ $item->quantity }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $sale->effectiveSoldAt()->translatedFormat('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($sale->expectedPayableAmount()) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($sale->paidAmountValue()) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ bccomp($sale->remainingAmountValue(), '0', 2) === 1 ? 'text-amber-800' : 'text-neutral-700' }}">{{ \App\Support\Money::usd($sale->remainingAmountValue()) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-0.5">
                                <a
                                    href="{{ route('sales.show', [$sale->branch, $sale]) }}"
                                    class="app-icon-btn"
                                    title="Voir la vente"
                                >
                                    <span class="sr-only">Voir</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-neutral-500">
                            @if (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) || ($filters['pos_terminal_id'] ?? null) || ($filters['payment_type'] ?? null))
                                Aucune vente pour cette période.
                            @else
                                Aucune vente enregistrée.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($sales->hasPages() || $sales->total() > 0)
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-neutral-600">
                @if ($sales->total() > 0)
                    Affichage de {{ $sales->firstItem() }} à {{ $sales->lastItem() }} sur {{ $sales->total() }} vente{{ $sales->total() > 1 ? 's' : '' }}
                @else
                    0 vente
                @endif
            </p>
            {{ $sales->links() }}
        </div>
    @endif
</x-app-layout>
