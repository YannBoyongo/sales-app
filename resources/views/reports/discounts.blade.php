<x-app-layout>
    <x-slot name="header">Liste de remises</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Rapports</p>
                    <h1 class="app-page-title">Liste de remises</h1>
                    <p class="app-page-desc max-w-3xl">
                        Remises par ligne de vente sur la période : prix, remise, montant payé, succursale et vendeur.
                    </p>
                </div>
                @include('reports.partials.print-button', ['route' => 'reports.discounts.pdf'])
            </div>
        </x-slot>

        @include('reports.partials.date-filter', ['action' => route('reports.discounts')])

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3">Produit</th>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                        <th class="px-4 py-3">Lieu</th>
                        <th class="px-4 py-3">Utilisateur</th>
                        <th class="px-4 py-3 text-right">Qté</th>
                        <th class="px-4 py-3 text-right">Prix</th>
                        <th class="px-4 py-3 text-right">Remise</th>
                        <th class="px-4 py-3 text-right">Payé</th>
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
                            <td class="px-4 py-3 text-neutral-700">
                                <div>{{ $row->branch_name }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500">{{ $row->location_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $row->user_name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-900">{{ number_format((int) $row->quantity, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ \App\Support\Money::usd($row->original_amount) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">{{ bccomp((string) $row->approved_discount, '0', 2) === 1 ? \App\Support\Money::usd($row->approved_discount) : '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($row->amount_paid) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-14 text-center text-neutral-500">Aucune remise enregistrée pour cette période.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-neutral-200 bg-neutral-50/90">
                        <tr>
                            <td colspan="6" class="px-4 py-3 font-semibold text-neutral-900">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-700">{{ \App\Support\Money::usd($summaryOriginal) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ bccomp((string) $summaryApproved, '0', 2) === 1 ? \App\Support\Money::usd($summaryApproved) : '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($summaryPaid) }}</td>
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
