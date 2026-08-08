<x-app-layout>
    <x-slot name="header">Liste de cautions</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Rapports</p>
                    <h1 class="app-page-title">Liste de cautions</h1>
                    <p class="app-page-desc max-w-3xl">
                        Dépôts et utilisations de caution par client sur la période. Une ligne par client.
                    </p>
                </div>
                @include('reports.partials.print-button', ['route' => 'reports.cautions.pdf'])
            </div>
        </x-slot>

        @include('reports.partials.date-filter', ['action' => route('reports.cautions')])

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                        <th class="px-4 py-3 text-right">Dépôts</th>
                        <th class="px-4 py-3 text-right">Utilisations</th>
                        <th class="px-4 py-3 text-right">Net période</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 bg-white">
                    @forelse ($rows as $row)
                        @php
                            $net = bcsub((string) $row->total_deposits, (string) $row->total_usages, 2);
                        @endphp
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-3 py-3 text-center tabular-nums text-neutral-500">{{ ($rows->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $row->name }}</div>
                                @if ($row->phone)
                                    <div class="mt-0.5 text-xs text-neutral-500">{{ $row->phone }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-700">{{ \App\Http\Controllers\EntryListReportController::formatReportDate($row->movement_date) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-800">{{ \App\Support\Money::usd($row->total_deposits) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ \App\Support\Money::usd($row->total_usages) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-sky-900">{{ \App\Support\Money::usd($net) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-neutral-500">Aucune caution enregistrée pour cette période.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-neutral-200 bg-neutral-50/90">
                        <tr>
                            <td colspan="3" class="px-4 py-3 font-semibold text-neutral-900">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-900">{{ \App\Support\Money::usd($summaryDeposits) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($summaryUsages) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-sky-950">{{ \App\Support\Money::usd(bcsub((string) $summaryDeposits, (string) $summaryUsages, 2)) }}</td>
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
