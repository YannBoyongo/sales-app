<x-app-layout>
    <x-slot name="header">Liste de transferts</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Rapports</p>
                    <h1 class="app-page-title">Liste de transferts</h1>
                    <p class="app-page-desc max-w-3xl">
                        Quantités transférées par produit (transferts confirmés), regroupées sur la période. Une ligne par produit.
                    </p>
                </div>
                @include('reports.partials.print-button', ['route' => 'reports.transfers.pdf'])
            </div>
        </x-slot>

        <form method="GET" action="{{ route('reports.transfers') }}" class="app-filter-bar mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
                <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div class="flex items-end gap-2 sm:col-span-2">
                <button type="submit" class="app-btn-primary">Filtrer</button>
                <a href="{{ route('reports.transfers') }}" class="app-btn-secondary">Réinitialiser</a>
            </div>
        </form>

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3">Produit</th>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Origine</th>
                        <th class="px-4 py-3">Destination</th>
                        <th class="px-4 py-3 text-right">Quantité</th>
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
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-700">{{ \App\Http\Controllers\TransferListReportController::formatReportDate($row->movement_date) }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ \App\Http\Controllers\TransferListReportController::formatTransferScope($row->transfer_scopes) }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $row->origins ?: '-' }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $row->destinations ?: '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ number_format((int) $row->total_quantity, 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center text-neutral-500">Aucun transfert confirmé pour cette période.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-neutral-200 bg-neutral-50/90">
                        <tr>
                            <td colspan="6" class="px-4 py-3 font-semibold text-neutral-900">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ number_format($summaryQuantity, 0, ',', ' ') }}</td>
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
