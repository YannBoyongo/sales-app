<x-app-layout>
    <x-slot name="header">Fiche de stock</x-slot>

    <x-caisse-flow max-width="max-w-5xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Stock</p>
                    <h1 class="app-page-title">{{ $product->name }}</h1>
                    <p class="app-page-desc">
                        @if ($product->sku)
                            <span class="font-mono text-sm text-neutral-600">{{ $product->sku }}</span>
                            -
                        @endif
                        {{ $product->department?->name ?? 'Sans département' }}
                        -
                        @if ($selectedLocation)
                            Emplacement : {{ $selectedLocation->name }}
                        @elseif ($selectedBranch)
                            Branche : {{ $selectedBranch->name }} (tous emplacements)
                        @else
                            Toutes branches et emplacements
                        @endif
                    </p>
                </div>
                <a href="{{ route('stocks.valuation', $valuationBackParams) }}" class="app-btn-secondary shrink-0">
                    Retour à la valorisation
                </a>
            </div>
        </x-slot>

        <div class="mb-4 flex flex-wrap items-center gap-4 text-sm text-neutral-600">
            <span>Stock actuel : <strong class="tabular-nums text-neutral-900">{{ number_format($currentStock, 0, ',', ' ') }}</strong></span>
            <span>{{ count($ledgerRows) - 1 }} mouvement{{ count($ledgerRows) - 1 !== 1 ? 's' : '' }} enregistré{{ count($ledgerRows) - 1 !== 1 ? 's' : '' }}</span>
        </div>

        <div class="overflow-x-auto border border-neutral-300 bg-white">
            <table class="stock-ledger-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-left">Origine / Destination</th>
                        <th class="px-3 py-2 text-right">Entrée</th>
                        <th class="px-3 py-2 text-right">Sortie</th>
                        <th class="px-3 py-2 text-right">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ledgerRows as $row)
                        <tr @class(['bg-neutral-50/80' => $row['is_opening']])>
                            <td class="px-3 py-2 whitespace-nowrap tabular-nums text-neutral-700">
                                @if ($row['date'])
                                    {{ $row['date']->format('d/m/y') }}
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-neutral-700">
                                @if ($row['type'])
                                    {{ $row['type'] }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-neutral-900">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium text-neutral-900">
                                {{ $row['entry'] !== null ? number_format($row['entry'], 0, ',', ' ') : '' }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium text-neutral-900">
                                {{ $row['exit'] !== null ? number_format($row['exit'], 0, ',', ' ') : '' }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-neutral-900">
                                {{ number_format($row['stock'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-caisse-flow>
</x-app-layout>
