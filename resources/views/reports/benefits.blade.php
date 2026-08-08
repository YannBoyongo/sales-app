<x-app-layout>
    <x-slot name="header">Bénéfices par article</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Rapports</p>
                    <h1 class="app-page-title">Bénéfices par article</h1>
                    <p class="app-page-desc max-w-3xl">
                        Ventes avec coût d’achat connu (lots) et bénéfice ligne = vente − coût. Les articles sans coût (ancien stock) restent visibles mais sans bénéfice.
                    </p>
                </div>
                @include('reports.partials.print-button', ['route' => 'reports.benefits.pdf'])
            </div>
        </x-slot>

        <form method="GET" action="{{ route('reports.benefits') }}" class="app-filter-bar mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
                <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
                <label for="product_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Produit</label>
                <select id="product_id" name="product_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Tous</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>
                            {{ $product->name }}@if ($product->sku) ({{ $product->sku }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cost_scope" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Coût</label>
                <select id="cost_scope" name="cost_scope" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="all" @selected(($filters['cost_scope'] ?? '') === 'all')>Tous</option>
                    <option value="with_cost" @selected(($filters['cost_scope'] ?? '') === 'with_cost')>Avec coût / bénéfice</option>
                    <option value="without_cost" @selected(($filters['cost_scope'] ?? '') === 'without_cost')>Sans coût (legacy)</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="app-btn-primary">Filtrer</button>
                <a href="{{ route('reports.benefits') }}" class="app-btn-secondary">Réinitialiser</a>
            </div>
        </form>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Lignes</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-neutral-900">{{ number_format($summary['lines']) }}</p>
                <p class="mt-0.5 text-xs text-neutral-500">{{ number_format($summary['quantity']) }} unité(s)</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Ventes</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($summary['revenue']) }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Coût d’achat</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($summary['cost']) }}</p>
                @if ($summary['unknown_cost_lines'] > 0)
                    <p class="mt-0.5 text-xs text-amber-700">{{ $summary['unknown_cost_lines'] }} ligne(s) sans coût</p>
                @endif
            </div>
            <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary">Bénéfice</p>
                <p class="mt-1 text-xl font-semibold tabular-nums {{ $summary['benefit'] < 0 ? 'text-red-700' : 'text-primary' }}">
                    {{ \App\Support\Money::usd($summary['benefit']) }}
                </p>
                <p class="mt-0.5 text-xs text-neutral-500">Sur les lignes avec coût connu</p>
            </div>
        </div>

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Vente</th>
                        <th class="px-4 py-3">Produit</th>
                        <th class="px-4 py-3">Lot</th>
                        <th class="px-4 py-3 text-right">Qté</th>
                        <th class="px-4 py-3 text-right">P.U. vente</th>
                        <th class="px-4 py-3 text-right">P.U. coût</th>
                        <th class="px-4 py-3 text-right">Vente</th>
                        <th class="px-4 py-3 text-right">Coût</th>
                        <th class="px-4 py-3 text-right">Bénéfice</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($items as $item)
                        @php
                            $revenue = (float) $item->line_total - (float) ($item->discount_amount ?? 0);
                        @endphp
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-700">
                                {{ $item->sale?->sold_at?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($item->sale && $item->branch_id)
                                    <a href="{{ route('sales.show', [$item->branch_id, $item->sale_id]) }}" class="font-medium text-primary hover:underline">
                                        {{ $item->sale->reference ?? '#'.$item->sale_id }}
                                    </a>
                                @else
                                    <span class="text-neutral-500">-</span>
                                @endif
                                @if ($item->branch)
                                    <div class="text-xs text-neutral-400">{{ $item->branch->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $item->product?->name ?? '-' }}</div>
                                @if ($item->product?->sku)
                                    <div class="text-xs text-neutral-500">{{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $item->batch_number ?: '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($item->unit_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ $item->unit_cost !== null ? \App\Support\Money::usd($item->unit_cost) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($revenue) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ $item->cost_total !== null ? \App\Support\Money::usd($item->cost_total) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $item->benefit !== null && (float) $item->benefit < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                                {{ $item->benefit !== null ? \App\Support\Money::usd($item->benefit) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-neutral-500">
                                Aucune vente pour cette période.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </x-caisse-flow>
</x-app-layout>
