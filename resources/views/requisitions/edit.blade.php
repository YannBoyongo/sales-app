<x-app-layout>
    <x-slot name="header">Modifier - {{ $requisition->reference }}</x-slot>

    @php
        $catalogForJs = $catalogItems->map(fn ($item) => [
            'product_id' => (int) $item->product_id,
            'product_name' => $item->product?->name ?? '-',
            'product_sku' => $item->product?->sku,
            'department_name' => $item->product?->department?->name,
            'total_quantity' => (int) $item->total_quantity,
        ])->values();
    @endphp

    <div
        x-data="{
            canEdit: @js($canEditItems),
            expenses: @js((float) ($requisition->expenses ?? 0)),
            catalog: @js($catalogForJs),
            catalogSearch: '',
            catalogPickerOpen: false,
            items: @js($requisitionItems).map((item, index) => ({
                ...item,
                _uid: 'req-' + String(item.product_id) + '-' + index + '-' + Math.random().toString(36).slice(2, 8),
            })),
            get filteredCatalog() {
                const q = String(this.catalogSearch || '').trim().toLowerCase();
                if (q.length < 1) {
                    return this.catalog;
                }
                return this.catalog.filter((item) => {
                    const haystack = [
                        item.product_name,
                        item.product_sku,
                        item.department_name,
                    ].filter(Boolean).join(' ').toLowerCase();
                    return haystack.includes(q);
                });
            },
            get catalogSuggestions() {
                const q = String(this.catalogSearch || '').trim().toLowerCase();
                if (q.length < 1) {
                    return this.catalog.slice(0, 12);
                }
                return this.filteredCatalog.slice(0, 20);
            },
            itemKey(item) {
                return item._uid;
            },
            hasItem(productId) {
                return this.items.some((item) => Number(item.product_id) === Number(productId));
            },
            addItem(payload) {
                if (! this.canEdit) {
                    return;
                }
                this.items.push({
                    _uid: 'req-' + String(payload.product_id) + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8),
                    product_id: payload.product_id,
                    quantity: 1,
                    batch_number: '',
                    unit_price: 0,
                    tax: 0,
                    other: 0,
                    cost: 0,
                    product_name: payload.product_name,
                    product_sku: payload.product_sku,
                });
            },
            pickCatalogItem(item, keepSearch = false) {
                this.addItem({
                    product_id: item.product_id,
                    product_name: item.product_name,
                    product_sku: item.product_sku,
                });
                if (! keepSearch) {
                    this.catalogSearch = '';
                    this.catalogPickerOpen = false;
                }
            },
            removeItem(index) {
                if (! this.canEdit) {
                    return;
                }
                this.items.splice(index, 1);
            },
        }"
        class="space-y-6"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="app-page-eyebrow">Achats</p>
                        <h1 class="app-page-title">Modifier {{ $requisition->reference }}</h1>
                        <p class="app-page-desc max-w-2xl">
                            Sélectionnez les articles à gauche, puis confirmez pour enregistrer la réquisition.
                        </p>
                        <div class="mt-4 flex flex-wrap items-end gap-4">
                            <div>
                                <label for="requisition_date" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date</label>
                                <input
                                    id="requisition_date"
                                    form="requisition-items-form"
                                    name="date"
                                    type="date"
                                    value="{{ old('date', $requisition->date?->toDateString()) }}"
                                    required
                                    class="mt-1 block rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                />
                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                            </div>
                            <input type="hidden" form="requisition-items-form" name="expenses" :value="expenses">
                            <div class="pb-0.5">
                                @if ($requisition->status === \App\Models\Requisition::STATUS_CONFIRMED)
                                    <span class="app-badge-success">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->status === \App\Models\Requisition::STATUS_REJECTED)
                                    <span class="app-badge-danger">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->isEditable())
                                    <span class="app-badge-warning">{{ $requisition->statusLabel() }}</span>
                                @else
                                    <span class="app-badge-neutral">{{ $requisition->statusLabel() }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('requisitions.show', $requisition) }}" class="app-btn-secondary">Voir</a>
                        <a href="{{ route('requisitions.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                            Retour
                        </a>
                    </div>
                </div>
            </x-slot>

            <div class="grid items-start gap-6 px-1 sm:gap-8 lg:grid-cols-2">
                <section class="app-panel flex min-h-[28rem] flex-col overflow-hidden">
                    <div class="app-panel-header flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-neutral-900">
                            {{ ($filters['stock_scope'] ?? 'out_of_stock') === 'all' ? 'Tous les articles' : 'Rupture de stock' }}
                        </h2>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($filters['stock_scope'] ?? 'out_of_stock') === 'out_of_stock' ? 'bg-red-100 text-red-800' : 'bg-neutral-200 text-neutral-800' }}"
                            x-text="catalogSearch.trim() ? (filteredCatalog.length + ' / ' + catalog.length) : catalog.length"
                        >{{ $catalogItems->count() }}</span>
                    </div>

                    <form method="GET" action="{{ route('requisitions.edit', $requisition) }}" class="grid gap-3 border-b border-neutral-100 bg-white px-4 py-4 sm:grid-cols-2 lg:grid-cols-3 sm:px-5">
                        <div>
                            <label for="stock_scope" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Articles</label>
                            <select
                                id="stock_scope"
                                name="stock_scope"
                                class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            >
                                <option value="all" @selected(($filters['stock_scope'] ?? '') === 'all')>Tous les articles</option>
                                <option value="out_of_stock" @selected(($filters['stock_scope'] ?? 'out_of_stock') === 'out_of_stock')>Rupture de stock</option>
                            </select>
                        </div>
                        <div>
                            <label for="department_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Catégorie</label>
                            <select
                                id="department_id"
                                name="department_id"
                                class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            >
                                <option value="">Toutes</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
                            <button type="submit" class="app-btn-primary">Filtrer</button>
                            <a href="{{ route('requisitions.edit', $requisition) }}" class="app-btn-secondary">Réinitialiser</a>
                        </div>
                    </form>

                    <div class="border-b border-neutral-100 bg-white px-4 py-3 sm:px-5">
                        <label for="catalog-search" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Rechercher un article</label>
                        <div class="relative mt-1" @click.outside="catalogPickerOpen = false">
                            <input
                                id="catalog-search"
                                type="search"
                                autocomplete="off"
                                x-model="catalogSearch"
                                @focus="catalogPickerOpen = true"
                                @input="catalogPickerOpen = true"
                                @keydown.escape.prevent="catalogPickerOpen = false"
                                placeholder="Nom, code ou catégorie…"
                                class="block w-full rounded-lg border-neutral-300 py-2.5 pl-10 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            />
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <button
                                type="button"
                                x-show="catalogSearch.length > 0"
                                x-cloak
                                @click="catalogSearch = ''; catalogPickerOpen = false"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-xs font-medium text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800"
                            >
                                Effacer
                            </button>
                            <div
                                x-show="catalogPickerOpen && catalogSuggestions.length && catalogSearch.trim().length > 0"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-neutral-200 bg-white py-1 shadow-xl"
                            >
                                <template x-for="item in catalogSuggestions" :key="'suggest-' + item.product_id">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-primary/5"
                                        @click="pickCatalogItem(item)"
                                    >
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-neutral-900" x-text="item.product_name"></span>
                                            <span class="mt-0.5 block truncate text-xs text-neutral-500">
                                                <span x-text="item.product_sku || '—'"></span>
                                                <span x-show="item.department_name"> · <span x-text="item.department_name"></span></span>
                                            </span>
                                        </span>
                                        <span
                                            class="shrink-0 tabular-nums text-xs font-semibold"
                                            :class="item.total_quantity <= 0 ? 'text-red-700' : 'text-neutral-700'"
                                            x-text="item.total_quantity"
                                        ></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <p class="mt-1.5 text-xs text-neutral-500" x-show="catalogSearch.trim().length > 0" x-cloak>
                            <span x-text="filteredCatalog.length"></span> résultat(s) dans la liste ci-dessous
                        </p>
                    </div>

                    <div class="min-h-0 flex-1 overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="sticky top-0 z-10 bg-primary-dark text-left text-xs font-semibold uppercase tracking-wide text-white">
                                <tr>
                                    <th class="px-4 py-3 sm:px-5">Produit</th>
                                    <th class="px-4 py-3 text-right sm:px-5">Stock total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <template x-for="item in filteredCatalog" :key="'catalog-' + item.product_id">
                                    <tr
                                        class="transition-colors"
                                        :class="[
                                            canEdit ? 'cursor-pointer hover:bg-primary/5' : 'hover:bg-neutral-50/80',
                                            canEdit && hasItem(item.product_id) ? 'bg-primary/5' : '',
                                        ]"
                                        @click="canEdit && pickCatalogItem(item, true)"
                                        title="Ajouter à la réquisition"
                                    >
                                        <td class="px-4 py-3 sm:px-5">
                                            <div class="font-medium text-neutral-900" x-text="item.product_name"></div>
                                            <div class="text-xs text-neutral-500" x-show="item.product_sku" x-text="item.product_sku"></div>
                                            <div class="text-xs text-neutral-400" x-show="item.department_name" x-text="item.department_name"></div>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right tabular-nums font-medium sm:px-5"
                                            :class="item.total_quantity <= 0 ? 'text-red-700' : 'text-neutral-900'"
                                            x-text="item.total_quantity"
                                        ></td>
                                    </tr>
                                </template>
                                <tr x-show="catalog.length === 0">
                                    <td colspan="2" class="px-4 py-10 text-center text-neutral-500 sm:px-5">
                                        {{ ($filters['stock_scope'] ?? 'out_of_stock') === 'out_of_stock' ? 'Aucun produit en rupture de stock.' : 'Aucun article trouvé.' }}
                                    </td>
                                </tr>
                                <tr x-show="catalog.length > 0 && filteredCatalog.length === 0" x-cloak>
                                    <td colspan="2" class="px-4 py-10 text-center text-neutral-500 sm:px-5">
                                        Aucun article ne correspond à votre recherche.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="app-panel flex min-h-[28rem] flex-col overflow-hidden">
                    <div class="app-panel-header flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-neutral-900">Articles de la réquisition</h2>
                        <span class="inline-flex rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-800" x-text="items.length"></span>
                    </div>

                    <form
                        id="requisition-items-form"
                        action="{{ route('requisitions.items.sync', $requisition) }}"
                        method="POST"
                        class="flex min-h-0 flex-1 flex-col"
                        @submit="if (canEdit && ! confirm('Enregistrer les articles (statut En attente) ?')) { $event.preventDefault(); }"
                    >
                        @csrf
                        @if (! empty($filters['stock_scope']))
                            <input type="hidden" name="stock_scope" value="{{ $filters['stock_scope'] }}">
                        @endif
                        @if (! empty($filters['department_id']))
                            <input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">
                        @endif

                        <div class="min-h-0 flex-1 overflow-x-auto">
                            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 sm:px-5">Produit</th>
                                        <th class="px-4 py-3 text-right sm:px-5">Quantité</th>
                                        @if ($canEditItems)
                                            <th class="px-4 py-3 text-right sm:px-5">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-for="(item, index) in items" :key="itemKey(item)">
                                        <tr class="transition-colors hover:bg-neutral-50/80">
                                            <td class="px-4 py-3 sm:px-5">
                                                <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                                <input type="hidden" :name="'items[' + index + '][batch_number]'" :value="item.batch_number ?? ''">
                                                <input type="hidden" :name="'items[' + index + '][unit_price]'" :value="item.unit_price ?? 0">
                                                <input type="hidden" :name="'items[' + index + '][tax]'" :value="item.tax ?? 0">
                                                <div class="font-medium text-neutral-900" x-text="item.product_name"></div>
                                                <div class="text-xs text-neutral-500" x-show="item.product_sku" x-text="item.product_sku"></div>
                                                <div class="mt-0.5 text-xs text-neutral-400">
                                                    Prix / taxe à saisir sur la fiche réquisition.
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right sm:px-5">
                                                @if ($canEditItems)
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        :name="'items[' + index + '][quantity]'"
                                                        x-model.number="item.quantity"
                                                        class="ml-auto block w-20 rounded-lg border-neutral-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary"
                                                    >
                                                @else
                                                    <span class="tabular-nums" x-text="item.quantity"></span>
                                                @endif
                                            </td>
                                            @if ($canEditItems)
                                                <td class="px-4 py-3 text-right sm:px-5">
                                                    <button
                                                        type="button"
                                                        class="text-xs font-semibold text-red-700 hover:text-red-800"
                                                        @click="removeItem(index)"
                                                    >
                                                        Retirer
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    </template>
                                    <tr x-show="items.length === 0">
                                        <td colspan="{{ $canEditItems ? 3 : 2 }}" class="px-4 py-10 text-center text-neutral-500 sm:px-5">
                                            {{ $canEditItems ? 'Cliquez un article à gauche pour l’ajouter.' : 'Aucun article dans cette réquisition.' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if ($canEditItems)
                            <div class="shrink-0 border-t border-neutral-100 bg-slate-50/80 px-4 py-4 sm:px-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-neutral-500" x-text="items.length ? (items.length + ' article(s) prêt(s) à enregistrer') : 'Vous pouvez confirmer pour enregistrer la date, même sans article.'"></p>
                                    <button type="submit" class="app-btn-primary w-full sm:w-auto">
                                        Enregistrer
                                    </button>
                                </div>
                            </div>
                        @endif
                    </form>
                </section>
            </div>
        </x-caisse-flow>
    </div>
</x-app-layout>
