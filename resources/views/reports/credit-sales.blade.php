<x-app-layout>
    <x-slot name="header">Liste des ventes crédit</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Rapports</p>
                    <h1 class="app-page-title">Liste des ventes crédit</h1>
                    <p class="app-page-desc max-w-3xl">
                        Ventes à crédit par produit sur la période, regroupées par article. Une ligne par produit.
                    </p>
                </div>
                @include('reports.partials.print-button', ['route' => 'reports.credit-sales.pdf'])
            </div>
        </x-slot>

        @include('reports.partials.date-filter', ['action' => route('reports.credit-sales')])

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3">Produit</th>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                        <th class="px-4 py-3 whitespace-nowrap">Échéance</th>
                        <th class="px-4 py-3">Client(s)</th>
                        <th class="px-4 py-3 text-right">Quantité</th>
                        <th class="px-4 py-3 text-right">Montant</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 bg-white">
                    @forelse ($rows as $row)
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-3 py-3 text-center tabular-nums text-neutral-500">{{ ($rows->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $row->product_name }}</div>
                                @if ($row->product_sku)
                                    <div class="mt-0.5 font-mono text-xs text-neutral-500">{{ $row->product_sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-700">{{ \App\Http\Controllers\EntryListReportController::formatReportDate($row->movement_date) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-700">{{ $row->due_dates ?: '-' }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $row->clients ?: '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-900">{{ number_format((int) $row->total_quantity, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($row->total_amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center text-neutral-500">Aucune vente crédit pour cette période.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-neutral-200 bg-neutral-50/90">
                        <tr>
                            <td colspan="5" class="px-4 py-3 font-semibold text-neutral-900">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ number_format($summaryQuantity, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($summaryAmount) }}</td>
                        </tr>
                        <tr class="border-t border-neutral-200">
                            <td colspan="6" class="px-4 py-3 text-neutral-700">Solde crédit restant (ventes de la période)</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-amber-900">{{ \App\Support\Money::usd($summaryRemaining) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </x-caisse-flow>
</x-app-layout>
