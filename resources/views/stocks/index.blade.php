@php
    $showStockAdjustment = auth()->user()?->isAdmin()
        && isset($adjustmentProducts, $adjustmentLocations)
        && $adjustmentProducts->isNotEmpty()
        && $adjustmentLocations->isNotEmpty();
@endphp

<x-app-layout>
    <x-slot name="header">Stocks par emplacement</x-slot>

    <div
        @if ($showStockAdjustment)
            x-data="stockAdjustmentModal({ currentQuantityUrl: @js(route('stocks.current-quantity')) })"
            @keydown.escape.window="if (open) open = false"
        @endif
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Stock</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Stocks par emplacement</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-neutral-600">
                            Une ligne par produit ; chaque colonne correspond à un emplacement. Les cellules en <span class="rounded bg-red-100 px-1 text-red-900">rouge</span> indiquent un stock strictement sous le seuil (emplacement ou seuil global du produit).
                            @if (auth()->user()->isPosUser())
                                <span class="mt-2 block text-neutral-600">Votre branche et les colonnes affichées correspondent à votre affectation : seuls les emplacements liés à vos terminaux POS assignés apparaissent (pas les autres emplacements de la branche).</span>
                            @endif
                            @if (auth()->user()->isAdmin())
                                <span class="mt-2 block text-neutral-500">En tant qu’administrateur, vous pouvez corriger le stock physique via « Ajuster une quantité » (enregistré dans les mouvements de stock).</span>
                            @endif
                        </p>
                    </div>
                    @if ($showStockAdjustment)
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-neutral-200/90 bg-white/90 px-5 py-2.5 text-sm font-semibold text-neutral-800 shadow-md shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm transition hover:border-primary/30 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            @click="openModal()"
                        >
                            Ajuster une quantité
                        </button>
                    @endif
                </div>
            </x-slot>

            @if ($errors->has('adjustment'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('adjustment') }}</div>
            @endif

            @if ($stockBranches->isNotEmpty() && $stockBranches->count() > 1)
                <form method="get" action="{{ route('stocks.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
                    <div class="min-w-[12rem]">
                        <label for="stock-branch-filter" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Branche</label>
                        <select
                            id="stock-branch-filter"
                            name="branch"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            onchange="this.form.submit()"
                        >
                            @foreach ($stockBranches as $b)
                                <option value="{{ $b->id }}" @selected($selectedBranch?->id === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            @elseif ($selectedBranch)
                <p class="mb-6 text-sm text-neutral-600">
                    Branche : <span class="font-semibold text-neutral-900">{{ $selectedBranch->name }}</span>
                </p>
            @endif

            @if ($stockBranches->isEmpty())
                <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-8 text-sm text-neutral-600 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    Aucune branche accessible pour afficher les stocks.
                </div>
            @elseif ($locations->isEmpty())
                <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-8 text-sm text-neutral-600 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    Aucun emplacement dans votre périmètre pour la branche sélectionnée.
                </div>
            @else
                <div class="overflow-x-auto rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                            <tr>
                                <th scope="col" class="sticky left-0 z-20 min-w-[12rem] border-r border-neutral-200 bg-neutral-50/90 px-4 py-3 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">Produit</th>
                                @foreach ($locations as $loc)
                                    <th scope="col" class="min-w-[6.5rem] whitespace-nowrap px-3 py-3 text-right" title="{{ $loc->branch->name }} — {{ $loc->name }}">
                                        <span class="block max-w-[8rem] truncate">{{ $loc->name }}</span>
                                        @if ($stockBranches->count() > 1)
                                            <span class="block max-w-[8rem] truncate font-normal normal-case text-neutral-400">{{ $loc->branch->name }}</span>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($products as $product)
                                <tr class="group hover:bg-neutral-50/80">
                                    <th scope="row" class="sticky left-0 z-10 border-r border-neutral-200 bg-white px-4 py-3 text-left font-medium text-neutral-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.06)] group-hover:bg-neutral-50/80">
                                        <span class="block">{{ $product->name }}</span>
                                        @if ($product->sku)
                                            <span class="mt-0.5 block text-xs font-normal text-neutral-500">{{ $product->sku }}</span>
                                        @endif
                                    </th>
                                    @foreach ($locations as $loc)
                                        @php
                                            $stock = $matrix[$product->id][$loc->id] ?? null;
                                            $qty = $stock?->quantity ?? 0;
                                            if ($stock) {
                                                $warn = $stock->isBelowMinimum();
                                            } else {
                                                $warn = $product->minimum_stock !== null && $qty < (int) $product->minimum_stock;
                                            }
                                        @endphp
                                        <td class="px-3 py-3 text-right tabular-nums @if ($warn) bg-red-100 text-red-950 @endif">
                                            <span @class(['font-semibold' => $warn])>{{ $qty }}</span>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $locations->count() + 1 }}" class="px-4 py-8 text-center text-neutral-500">Aucun produit à afficher.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $products->links() }}</div>
            @endif

            @if ($showStockAdjustment)
                <div
                    x-show="open"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    x-transition.opacity
                >
                    <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="stock-adjust-title"
                        class="relative z-10 w-full max-w-md rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5"
                        @click.stop
                    >
                        <h2 id="stock-adjust-title" class="text-lg font-semibold text-neutral-900">Ajustement de stock</h2>
                        <p class="mt-1 text-sm text-neutral-600">Indiquez la quantité réelle comptée à l’emplacement choisi.</p>

                        <form action="{{ route('stocks.adjustment') }}" method="POST" class="mt-5 space-y-4" @submit="submitting = true">
                            @csrf
                            @if ($selectedBranch)
                                <input type="hidden" name="branch" value="{{ $selectedBranch->id }}" />
                            @endif
                            <div>
                                <label for="adj_product_id" class="block text-xs font-semibold text-neutral-700">Produit</label>
                                <select
                                    id="adj_product_id"
                                    name="product_id"
                                    required
                                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                    x-model="productId"
                                    @change="refreshCurrent()"
                                >
                                    <option value="">— Choisir —</option>
                                    @foreach ($adjustmentProducts as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}@if ($p->sku) ({{ $p->sku }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="adj_location_id" class="block text-xs font-semibold text-neutral-700">Emplacement</label>
                                <select
                                    id="adj_location_id"
                                    name="location_id"
                                    required
                                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                    x-model="locationId"
                                    @change="refreshCurrent()"
                                >
                                    <option value="">— Choisir —</option>
                                    @foreach ($adjustmentLocations as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->branch->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="rounded-lg border border-neutral-100 bg-neutral-50 px-3 py-2 text-sm">
                                <span class="text-neutral-600">Quantité actuelle en base :</span>
                                <span class="ml-1 font-semibold tabular-nums text-neutral-900" x-text="loading ? '…' : (currentQty === null ? '—' : currentQty)"></span>
                            </div>
                            <div>
                                <label for="adj_quantity" class="block text-xs font-semibold text-neutral-700">Nouvelle quantité</label>
                                <input
                                    id="adj_quantity"
                                    name="quantity"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                    x-model="newQty"
                                />
                            </div>
                            <div>
                                <label for="adj_notes" class="block text-xs font-semibold text-neutral-700">Commentaire (optionnel)</label>
                                <input
                                    id="adj_notes"
                                    name="notes"
                                    type="text"
                                    maxlength="500"
                                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                    placeholder="Ex. inventaire annuel"
                                />
                            </div>
                            <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                                <button type="button" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="open = false">
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95 disabled:opacity-50"
                                    :disabled="submitting || !productId || !locationId"
                                >
                                    Enregistrer l’ajustement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function stockAdjustmentModal(config) {
                        return {
                            open: false,
                            productId: '',
                            locationId: '',
                            newQty: '',
                            currentQty: null,
                            loading: false,
                            submitting: false,
                            openModal() {
                                this.open = true;
                                this.productId = '';
                                this.locationId = '';
                                this.newQty = '';
                                this.currentQty = null;
                                this.loading = false;
                                this.submitting = false;
                            },
                            async refreshCurrent() {
                                if (!this.productId || !this.locationId) {
                                    this.currentQty = null;
                                    return;
                                }
                                this.loading = true;
                                try {
                                    const url = new URL(config.currentQuantityUrl, window.location.origin);
                                    url.searchParams.set('product_id', this.productId);
                                    url.searchParams.set('location_id', this.locationId);
                                    const res = await fetch(url.toString(), {
                                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                    });
                                    if (!res.ok) throw new Error('fetch failed');
                                    const data = await res.json();
                                    this.currentQty = data.quantity;
                                    if (this.newQty === '' || this.newQty === null) {
                                        this.newQty = String(data.quantity);
                                    }
                                } catch {
                                    this.currentQty = null;
                                } finally {
                                    this.loading = false;
                                }
                            },
                        };
                    }
                </script>
            @endif
        </x-caisse-flow>
    </div>
</x-app-layout>
