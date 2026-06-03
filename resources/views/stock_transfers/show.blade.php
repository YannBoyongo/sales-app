<x-app-layout>
    <x-slot name="header">Transfert #{{ $stockTransfer->id }}</x-slot>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="app-page-title">Transfert #{{ $stockTransfer->id }}</h1>
            <p class="app-page-desc">Date : {{ $stockTransfer->transferred_at->translatedFormat('d/m/Y') }} — par {{ $stockTransfer->user->name }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            @if ($canCancelTransfer)
                <form
                    method="POST"
                    action="{{ route('stock-transfers.cancel', $stockTransfer) }}"
                    class="inline"
                    onsubmit="return confirm(@js($stockTransfer->isConfirmed()
                        ? 'Annuler ce transfert confirmé ? Les quantités seront réintégrées à l’emplacement source et retirées de la destination.'
                        : 'Annuler ce transfert en attente ? Aucun stock n’a encore été déplacé.'));"
                >
                    @csrf
                    <button
                        type="submit"
                        class="app-btn-danger"
                    >
                        Annuler le transfert
                    </button>
                </form>
            @endif
            @if ($canManageTransfer && $stockTransfer->isPending())
                <form
                    method="POST"
                    action="{{ route('stock-transfers.confirm', $stockTransfer) }}"
                    class="inline"
                    onsubmit="return confirm('Confirmer ce transfert ? Les stocks seront mis à jour à la source et à la destination selon les lignes ci-dessous.');"
                >
                    @csrf
                    <button
                        type="submit"
                        @class([
                            'app-btn-primary' => ! $stockTransfer->items->isEmpty(),
                            'app-btn-secondary cursor-not-allowed opacity-60' => $stockTransfer->items->isEmpty(),
                        ])
                        @disabled($stockTransfer->items->isEmpty())
                    >
                        Confirmer le transfert
                    </button>
                </form>
            @endif
            <a href="{{ route('stock-transfers.index') }}" class="app-btn-secondary">← Liste des transferts</a>
        </div>
    </div>

    @if (session('success'))
        <div class="app-alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->has('stock'))
        <div class="app-alert-danger" role="alert">{{ $errors->first('stock') }}</div>
    @endif

    {{-- Section 1 : détails du transfert --}}
    <div class="app-panel app-panel-body mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Détails du transfert</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Statut</dt>
                <dd class="mt-1">
                    @if ($stockTransfer->isPending())
                        <span class="app-badge-warning">{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_PENDING) }}</span>
                    @elseif ($stockTransfer->isCancelled())
                        <span class="app-badge-danger">{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_CANCELLED) }}</span>
                    @else
                        <span class="app-badge-success">{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_CONFIRMED) }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</dt>
                <dd class="mt-1 text-neutral-900">{{ \App\Models\StockTransfer::scopeLabel($stockTransfer->transfer_scope ?? \App\Models\StockTransfer::SCOPE_INTERNAL) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Date comptable</dt>
                <dd class="mt-1 text-neutral-900">{{ $stockTransfer->transferred_at->translatedFormat('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Source</dt>
                <dd class="mt-1 text-neutral-900">{{ $stockTransfer->fromLocation->name }} <span class="text-neutral-500">({{ $stockTransfer->fromLocation->branch->name }})</span></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Destination</dt>
                <dd class="mt-1 text-neutral-900">{{ $stockTransfer->toLocation->name }} <span class="text-neutral-500">({{ $stockTransfer->toLocation->branch->name }})</span></dd>
            </div>
        </dl>
        @if ($stockTransfer->notes)
            <p class="mt-4 text-sm text-neutral-700"><span class="font-medium">Notes :</span> {{ $stockTransfer->notes }}</p>
        @endif
    </div>

    @if ($canManageTransfer && $stockTransfer->isPending())
        {{-- Section 2 : ajout de lignes --}}
        <div
            class="app-panel app-panel-body mb-6"
            x-data="{
                products: @js($transferProducts),
                searchQuery: '',
                pickerOpen: false,
                pendingProduct: null,
                addQuantity: 1,
                addLineError: '',
                get filteredProducts() {
                    const q = String(this.searchQuery || '').trim().toLowerCase();
                    const all = this.products;
                    if (q.length < 1) return all.slice(0, 15);
                    return all.filter(p => {
                        const t = (String(p.label) + ' ' + String(p.name || '') + ' ' + String(p.sku || '')).toLowerCase();
                        return t.includes(q);
                    }).slice(0, 25);
                },
                clearPick() {
                    this.pendingProduct = null;
                    this.searchQuery = '';
                    this.pickerOpen = false;
                    this.addLineError = '';
                },
                addLine() {
                    this.addLineError = '';
                    if (!this.pendingProduct) {
                        this.addLineError = 'Choisissez un produit dans la liste.';
                        return;
                    }
                    const q = Number(this.addQuantity);
                    if (!Number.isFinite(q) || q < 1) {
                        this.addLineError = 'Indiquez une quantité valide (minimum 1).';
                        return;
                    }
                    if (q > this.pendingProduct.stock_qty) {
                        this.addLineError = 'Stock insuffisant à l’emplacement source pour cette quantité.';
                        return;
                    }
                    this.$refs.addItemForm.requestSubmit();
                },
            }"
        >
            <h2 class="text-base font-semibold text-neutral-900">Ajouter des articles</h2>
            <p class="mt-1 text-sm text-neutral-600">
                Recherchez un produit, indiquez la quantité, puis ajoutez. Les stocks à <strong>{{ $stockTransfer->fromLocation->name }}</strong> et <strong>{{ $stockTransfer->toLocation->name }}</strong> seront ajustés lorsque vous <strong>confirmez</strong> le transfert.
            </p>

            @if (count($transferProducts) === 0)
                <p class="app-alert-warning mt-4">
                    Aucun produit n’est disponible pour votre périmètre (ou le catalogue est vide).
                </p>
            @else
                <form
                    x-ref="addItemForm"
                    method="POST"
                    action="{{ route('stock-transfers.items.store', $stockTransfer) }}"
                    class="mt-4 space-y-3"
                >
                    @csrf
                    {{-- Search + quantité + bouton sur une même ligne (≥ sm) --}}
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-3">
                        <div class="relative min-w-0 flex-1" @click.outside="pickerOpen = false">
                            <label for="transfer-product-search" class="mb-1.5 block text-xs font-semibold text-neutral-700">Rechercher un produit</label>
                            <input
                                id="transfer-product-search"
                                type="search"
                                autocomplete="off"
                                x-model="searchQuery"
                                @focus="pickerOpen = true"
                                @input="pickerOpen = true; if (pendingProduct && searchQuery !== pendingProduct.label) pendingProduct = null"
                                placeholder="Nom, référence (SKU)…"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                            />
                            <div
                                x-show="pickerOpen && filteredProducts.length > 0 && products.length > 0"
                                x-cloak
                                class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-neutral-200 bg-white py-1 shadow-lg"
                            >
                                <template x-for="p in filteredProducts" :key="'tp-' + p.id">
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm text-neutral-900 hover:bg-neutral-50"
                                        @click="pendingProduct = p; searchQuery = p.label; pickerOpen = false; addLineError = ''"
                                    >
                                        <span class="min-w-0 flex-1" x-text="p.label"></span>
                                        <span class="shrink-0 text-xs tabular-nums text-neutral-500" x-text="'stock : ' + p.stock_qty"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="w-full shrink-0 sm:w-28">
                            <label for="transfer-add-qty" class="mb-1.5 block text-xs font-semibold text-neutral-700">Quantité</label>
                            <input
                                id="transfer-add-qty"
                                name="quantity"
                                type="number"
                                min="1"
                                x-model.number="addQuantity"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                            />
                        </div>
                        <button
                            type="button"
                            class="app-btn-primary w-full shrink-0 sm:ml-0 sm:w-auto sm:min-w-[11rem]"
                            @click="addLine()"
                        >
                            Ajouter au transfert
                        </button>
                    </div>

                    <div x-show="pendingProduct" x-cloak class="rounded-md border border-primary/25 bg-primary/5 px-3 py-2 text-sm text-neutral-800">
                        <span class="text-neutral-600">Sélection :</span>
                        <strong class="ml-1" x-text="pendingProduct.label"></strong>
                        <span class="ml-2 text-xs font-medium text-neutral-600">
                            — <span x-text="'disponible à la source : ' + pendingProduct.stock_qty"></span>
                        </span>
                        <button type="button" class="ml-2 text-xs font-semibold text-primary hover:underline" @click="clearPick()">Changer</button>
                    </div>

                    <input type="hidden" name="product_id" :value="pendingProduct ? pendingProduct.id : ''" />

                    <p x-show="addLineError" x-text="addLineError" class="text-sm font-medium text-red-600" x-cloak></p>
                    <x-input-error :messages="$errors->get('product_id')" class="text-sm" />
                    <x-input-error :messages="$errors->get('quantity')" class="text-sm" />
                </form>
            @endif
        </div>
    @endif

    {{-- Section 3 : lignes du transfert --}}
    <div class="app-panel">
        <div class="app-panel-header !py-3">
            <h2 class="text-sm font-semibold text-neutral-900">Articles à transférer</h2>
            <p class="mt-0.5 text-xs text-neutral-500">
                @if ($stockTransfer->isCancelled())
                    Ce transfert a été annulé.
                @elseif ($stockTransfer->isConfirmed())
                    Les mouvements de stock (type Transfert) ont été enregistrés pour cette date comptable.
                @else
                    Après confirmation, les mouvements apparaîtront dans « Mouvements de stock » avec la date du transfert.
                @endif
            </p>
        </div>
        @if ($stockTransfer->items->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-neutral-500">
                Aucune ligne pour l’instant.@if ($canManageTransfer && $stockTransfer->isPending()) Utilisez la section ci-dessus pour ajouter des produits.@endif
            </p>
        @else
            <div class="app-table-shell border-0 shadow-none">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Produit</th>
                            <th class="px-4 py-3 text-right">Quantité</th>
                            @if ($canManageTransfer && $stockTransfer->isPending())
                                <th class="w-28 px-4 py-3 text-right"><span class="sr-only">Retirer</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($stockTransfer->items as $line)
                            <tr class="hover:bg-neutral-50/80">
                                <td class="px-4 py-3 font-medium text-neutral-900">
                                    {{ $line->product->name }}@if ($line->product->sku) <span class="font-normal text-neutral-500">({{ $line->product->sku }})</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $line->quantity }}</td>
                                @if ($canManageTransfer && $stockTransfer->isPending())
                                    <td class="px-4 py-3 text-right">
                                        <form
                                            method="POST"
                                            action="{{ route('stock-transfers.items.destroy', [$stockTransfer, $line]) }}"
                                            class="inline"
                                            onsubmit="return confirm('Retirer cette ligne ? Aucun stock n’a encore été déplacé tant que le transfert n’est pas confirmé.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="text-sm font-semibold text-red-700 underline-offset-2 hover:text-red-800 hover:underline"
                                            >
                                                Retirer
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
