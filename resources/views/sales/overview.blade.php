<x-app-layout>
    <x-slot name="header">Ventes</x-slot>

    <x-page-header title="Ventes" action="Nouvelle vente" :action-href="route('sales.entry')" />

    @if ($errors->has('sale'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('sale') }}</div>
    @endif

    @if ($canApproveDiscounts && $pendingDiscountCount > 0)
        <div class="mb-4 flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between" role="status">
            <p>
                <span class="font-semibold">{{ $pendingDiscountCount }} remise{{ $pendingDiscountCount > 1 ? 's' : '' }}</span>
                en attente d’approbation administrateur.
            </p>
            <div class="flex flex-wrap gap-2">
                @if (request()->boolean('remise'))
                    <a href="{{ route('sales.overview', request()->except(['remise', 'page'])) }}" class="inline-flex items-center rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100">Toutes les ventes</a>
                @else
                    <a href="{{ route('sales.overview', array_merge(request()->except('page'), ['remise' => 1])) }}" class="inline-flex items-center rounded-md border border-primary/40 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/15">Voir les remises en attente</a>
                @endif
            </div>
        </div>
    @endif

    <div class="overflow-x-auto overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-3 py-3 whitespace-nowrap">Date</th>
                    <th class="px-3 py-3 whitespace-nowrap">Branche</th>
                    <th class="px-3 py-3 whitespace-nowrap">Référence</th>
                    <th class="px-3 py-3 whitespace-nowrap">Utilisateur</th>
                    <th class="px-3 py-3 whitespace-nowrap">Paiement</th>
                    <th class="px-3 py-3 whitespace-nowrap">Remise</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Total</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($sales as $sale)
                    <tr @class([
                        'hover:bg-neutral-50/80' => ! $sale->isPendingDiscount(),
                        'bg-amber-50/90 hover:bg-amber-100/80' => $sale->isPendingDiscount(),
                    ])>
                        <td class="px-3 py-3 text-neutral-600 whitespace-nowrap">{{ $sale->sold_at->translatedFormat('d/m/Y') }}</td>
                        <td class="px-3 py-3 font-medium text-neutral-900">{{ $sale->branch->name }}</td>
                        <td class="px-3 py-3 font-mono text-neutral-800">{{ $sale->reference }}</td>
                        <td class="px-3 py-3 text-neutral-700">{{ $sale->user?->name ?? '—' }}</td>
                        <td class="px-3 py-3">
                            @if ($sale->payment_type === 'credit')
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Crédit</span>
                            @else
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900">Cash</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            @if ($sale->isPendingDiscount())
                                <span class="inline-flex rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">À valider</span>
                            @elseif ($sale->discount_amount && (float) $sale->discount_amount > 0)
                                <span class="text-xs text-neutral-600">− {{ \App\Support\Money::usd($sale->discount_amount) }}</span>
                            @else
                                <span class="text-xs text-neutral-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($sale->total_amount) }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-0.5">
                                <a
                                    href="{{ route('sales.show', [$sale->branch, $sale]) }}"
                                    class="inline-flex rounded-md p-1.5 text-primary hover:bg-primary/10"
                                    title="Voir la vente"
                                >
                                    <span class="sr-only">Voir</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                @if ($canManageSales)
                                    <a
                                        href="{{ route('sales.edit', [$sale->branch, $sale]) }}"
                                        class="inline-flex rounded-md p-1.5 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Modifier la vente"
                                    >
                                        <span class="sr-only">Modifier</span>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <form
                                        action="{{ route('sales.destroy', [$sale->branch, $sale]) }}"
                                        method="POST"
                                        class="inline-flex"
                                        onsubmit="return confirm('Supprimer définitivement cette vente ? Le stock sera réintégré sur les emplacements concernés.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="inline-flex rounded-md p-1.5 text-red-600 hover:bg-red-50 hover:text-red-800"
                                            title="Supprimer la vente"
                                        >
                                            <span class="sr-only">Supprimer</span>
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-10 text-center text-neutral-500">Aucune vente enregistrée.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
</x-app-layout>
