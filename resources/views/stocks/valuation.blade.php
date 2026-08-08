<x-app-layout>
    <x-slot name="header">Valorisation stock</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div>
                <p class="app-page-eyebrow">Stock</p>
                <h1 class="app-page-title">Valorisation stock</h1>
                <p class="app-page-desc max-w-3xl">
                        @if ($aggregateByProduct ?? false)
                            Une ligne par produit, toutes branches et emplacements confondus, avec coût moyen pondéré (CMP) calculé sur les lots suivis. Le stock sans lot suivi n’est pas valorisé.
                        @else
                            Une ligne par produit et emplacement, avec coût moyen pondéré (CMP) calculé sur les lots suivis. Le stock sans lot suivi n’est pas valorisé.
                        @endif
                </p>
            </div>
        </x-slot>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="app-stat-card">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Valeur totale</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($summaryRow->total_value ?? 0) }}</p>
                <p class="mt-1 text-sm text-neutral-600">Lots suivis uniquement</p>
            </div>
            <div class="app-stat-card">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Quantité valorisée</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-neutral-900">{{ number_format((int) ($summaryRow->total_qty ?? 0), 0, ',', ' ') }}</p>
                <p class="mt-1 text-sm text-neutral-600">Unités avec coût connu</p>
            </div>
            <div class="app-stat-card @if ($untrackedQty > 0) border-amber-200/90 bg-amber-50/90 @endif">
                <p class="text-xs font-medium uppercase tracking-wide @if ($untrackedQty > 0) text-amber-800 @else text-neutral-500 @endif">Sans coût suivi</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums @if ($untrackedQty > 0) text-amber-900 @else text-neutral-900 @endif">{{ number_format($untrackedQty, 0, ',', ' ') }}</p>
                <p class="mt-1 text-sm @if ($untrackedQty > 0) text-amber-900/80 @else text-neutral-600 @endif">Unités hors lots (legacy)</p>
            </div>
        </div>

        @if ($stockBranches->isNotEmpty())
            <form method="get" action="{{ route('stocks.valuation') }}" class="app-filter-bar mb-6">
                @if ($stockBranches->count() > 1)
                    <div class="min-w-[12rem]">
                        <label for="branch" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Branche</label>
                        <select id="branch" name="branch" class="app-input mt-1">
                            <option value="" @selected($showAllBranches ?? false)>Toutes</option>
                            @foreach ($stockBranches as $branch)
                                <option value="{{ $branch->id }}" @selected(! ($showAllBranches ?? false) && $selectedBranch?->id === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($selectedBranch)
                    <input type="hidden" name="branch" value="{{ $selectedBranch->id }}" />
                @endif

                <div class="min-w-[12rem]">
                    <label for="location_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Emplacement</label>
                    <select id="location_id" name="location_id" class="app-input mt-1">
                        <option value="">Tous</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[12rem]">
                    <label for="department_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Département</label>
                    <select id="department_id" name="department_id" class="app-input mt-1">
                        <option value="">Tous</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) ($filters['department_id'] ?? 0) === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="app-btn-primary">Filtrer</button>
                    <a href="{{ route('stocks.valuation') }}" class="app-btn-secondary">Réinitialiser</a>
                </div>
            </form>
        @else
            <p class="mb-6 text-sm text-neutral-600">Aucune branche accessible pour afficher la valorisation.</p>
        @endif

        <div class="app-table-shell">
            <table class="valuation-table min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3">Produit</th>
                        <th class="px-4 py-3">Département</th>
                        <th class="px-4 py-3 text-right">CMP</th>
                        <th class="px-4 py-3 text-right">Quantité</th>
                        <th class="px-4 py-3 text-right">Valeur</th>
                        <th class="valuation-sales-col px-4 py-3 text-right">Prix vente</th>
                        <th class="valuation-sales-col px-4 py-3 text-right">Quantité</th>
                        <th class="valuation-sales-col px-4 py-3 text-right">Valeur vente</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 bg-white">
                    @forelse ($valuationLines as $line)
                        @php
                            $product = $productsById->get($line->product_id);
                            $lineKey = ($aggregateByProduct ?? false)
                                ? (string) $line->product_id
                                : $line->product_id.'-'.$line->location_id;
                            $lineBatches = $batchesByLine[$lineKey] ?? [];
                            $unitPrice = $product?->unit_price;
                            $salesValue = $unitPrice !== null
                                ? bcmul((string) $line->quantity, (string) $unitPrice, 2)
                                : null;
                            $ledgerParams = array_filter([
                                'branch' => ($showAllBranches ?? false) ? '' : $selectedBranch?->id,
                                'location_id' => ($aggregateByProduct ?? false) ? null : ($line->location_id ?? null),
                            ], static fn ($value) => $value !== null && $value !== '');
                        @endphp
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-3 py-3 text-center tabular-nums text-neutral-500">{{ ($valuationLines->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">
                                @if ($product)
                                    <a
                                        href="{{ route('stocks.products.show', ['product' => $product->id] + $ledgerParams) }}"
                                        class="font-medium text-primary underline-offset-2 hover:underline"
                                    >
                                        {{ $product->name }}
                                    </a>
                                @else
                                    <div class="font-medium text-neutral-900">-</div>
                                @endif
                                @if ($product?->sku)
                                    <div class="mt-0.5 font-mono text-xs text-neutral-500">{{ $product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $product?->department?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <span>{{ \App\Support\Money::usd($line->weighted_unit_cost) }}</span>
                                    @if (count($lineBatches) > 0)
                                        <div
                                            class="relative inline-flex"
                                            x-data="{
                                                open: false,
                                                positionTooltip() {
                                                    if (! this.open || ! this.$refs.trigger || ! this.$refs.tooltip) {
                                                        return;
                                                    }
                                                    const rect = this.$refs.trigger.getBoundingClientRect();
                                                    const tip = this.$refs.tooltip;
                                                    const top = rect.bottom + 8;
                                                    let left = rect.left + rect.width / 2 - tip.offsetWidth / 2;
                                                    left = Math.max(8, Math.min(left, window.innerWidth - tip.offsetWidth - 8));
                                                    tip.style.top = `${top}px`;
                                                    tip.style.left = `${left}px`;
                                                },
                                                showTooltip() {
                                                    this.open = true;
                                                    this.$nextTick(() => this.positionTooltip());
                                                },
                                                hideTooltip() {
                                                    this.open = false;
                                                },
                                                toggleTooltip() {
                                                    this.open = ! this.open;
                                                    if (this.open) {
                                                        this.$nextTick(() => this.positionTooltip());
                                                    }
                                                },
                                            }"
                                            @keydown.escape.window="hideTooltip()"
                                        >
                                            <button
                                                type="button"
                                                x-ref="trigger"
                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-500 transition hover:border-primary/30 hover:bg-primary/5 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                                @mouseenter="showTooltip()"
                                                @mouseleave="hideTooltip()"
                                                @focus="showTooltip()"
                                                @blur="hideTooltip()"
                                                @click.prevent="toggleTooltip()"
                                                aria-describedby="batch-tooltip-{{ $lineKey }}"
                                                aria-label="Détail des coûts par lot"
                                            >
                                                <span class="sr-only">Détail des coûts par lot</span>
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                            <div
                                                x-ref="tooltip"
                                                x-show="open"
                                                x-cloak
                                                x-transition
                                                @mouseenter="showTooltip()"
                                                @mouseleave="hideTooltip()"
                                                id="batch-tooltip-{{ $lineKey }}"
                                                role="tooltip"
                                                class="valuation-batch-tooltip @if ($aggregateByProduct ?? false) valuation-batch-tooltip--wide @endif"
                                            >
                                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Coûts par lot</p>
                                                <p class="mt-1 text-sm font-medium text-neutral-900">{{ $product?->name ?? '-' }}</p>
                                                <table>
                                                    <thead>
                                                        <tr class="text-left font-semibold uppercase tracking-wide text-neutral-500">
                                                            @if ($aggregateByProduct ?? false)
                                                                <th class="pb-1.5">Emplacement</th>
                                                            @endif
                                                            <th class="pb-1.5">Lot</th>
                                                            <th class="pb-1.5 text-right">Coût</th>
                                                            <th class="pb-1.5 text-right">Qté</th>
                                                            <th class="pb-1.5 text-right">Valeur</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-neutral-100">
                                                        @foreach ($lineBatches as $batch)
                                                            <tr>
                                                                @if ($aggregateByProduct ?? false)
                                                                    <td class="py-1.5 text-neutral-700">{{ $batch['location_name'] ?? '-' }}</td>
                                                                @endif
                                                                <td class="py-1.5 font-mono text-neutral-800">{{ $batch['batch_number'] }}</td>
                                                                <td class="py-1.5 text-right tabular-nums text-neutral-700">{{ \App\Support\Money::usd($batch['unit_cost']) }}</td>
                                                                <td class="py-1.5 text-right tabular-nums text-neutral-900">{{ $batch['quantity'] }}</td>
                                                                <td class="py-1.5 text-right tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd(bcmul((string) $batch['quantity'], (string) $batch['unit_cost'], 2)) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">{{ (int) $line->quantity }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($line->total_value) }}</td>
                            <td class="valuation-sales-col px-4 py-3 text-right tabular-nums font-medium">
                                {{ $unitPrice !== null ? \App\Support\Money::usd($unitPrice) : '-' }}
                            </td>
                            <td class="valuation-sales-col px-4 py-3 text-right tabular-nums font-medium">{{ (int) $line->quantity }}</td>
                            <td class="valuation-sales-col px-4 py-3 text-right tabular-nums font-semibold">
                                {{ $salesValue !== null ? \App\Support\Money::usd($salesValue) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-14 text-center text-neutral-500">
                                @if (($filters['location_id'] ?? null) || ($filters['department_id'] ?? null))
                                    Aucun stock valorisé pour ces critères.
                                @else
                                    Aucun stock avec coût suivi enregistré.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($valuationLines->isNotEmpty())
                    <tfoot class="border-t-2 border-neutral-200 bg-neutral-50/90">
                        <tr>
                            <td class="px-3 py-3"></td>
                            <td colspan="4" class="px-4 py-3 text-neutral-900">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($summaryRow->total_value ?? 0) }}</td>
                            <td class="valuation-sales-col px-4 py-3" colspan="2"></td>
                            <td class="valuation-sales-col px-4 py-3 text-right tabular-nums text-sky-950">{{ \App\Support\Money::usd($summaryRow->total_sales_value ?? 0) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($valuationLines->hasPages())
            <div class="mt-4">
                {{ $valuationLines->links() }}
            </div>
        @endif
    </x-caisse-flow>
</x-app-layout>
