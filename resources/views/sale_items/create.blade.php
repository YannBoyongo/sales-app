<x-app-layout>
    <x-slot name="header">Nouvelle vente — {{ $posTerminal->name }} — {{ $department->name }}</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Caisse</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Enregistrer une vente</h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                        Ajoutez des lignes, choisissez le paiement et validez. Le stock est mis à jour sur <strong class="font-semibold text-neutral-800">{{ $pointOfSale->name }}</strong>.
                    </p>
                    <p class="mt-3 inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-full border border-neutral-200/80 bg-white/80 px-4 py-1.5 text-sm text-neutral-700 shadow-sm backdrop-blur-sm">
                        <span class="text-neutral-500">Branche</span>
                        <strong class="text-neutral-900">{{ $branch->name }}</strong>
                        <span class="text-neutral-300">·</span>
                        <span class="text-neutral-500">Terminal</span>
                        <strong class="text-neutral-900">{{ $posTerminal->name }}</strong>
                        <span class="text-neutral-300">·</span>
                        <span class="text-neutral-500">Département</span>
                        <strong class="text-neutral-900">{{ $department->name }}</strong>
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            href="{{ route('sales.choose-department', [$branch, $posTerminal]) }}"
                            class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-primary/30 hover:text-primary"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            Autre département
                        </a>
                        <a
                            href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary"
                        >
                            Espace caisse
                        </a>
                    </div>
                </div>
                <a
                    href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-neutral-200/90 bg-white/90 px-5 py-2.5 text-sm font-semibold text-neutral-800 shadow-md shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm transition hover:border-primary/30 hover:text-primary lg:mt-10"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Retour caisse
                </a>
            </div>
        </x-slot>

        <x-slot name="stepper">
            <x-flow-sale-stepper :step="4" :total-steps="4" />
        </x-slot>

        @if ($errors->has('sale'))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('sale') }}</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form
                action="{{ route('sales.store', [$branch, $posTerminal, $department]) }}"
                method="POST"
                class="space-y-6"
                x-data="{
                    paymentType: @js(old('payment_type', 'cash')),
                    catalog: @js($saleCatalog),
                    rows: @js($saleLineRows),
                    posName: @js($pointOfSale->name),
                    searchQuery: '',
                    pendingProduct: null,
                    pickerOpen: false,
                    addQuantity: 1,
                    addLineError: '',
                    apply_sale_discount: @js(
                        filter_var(old('apply_sale_discount'), FILTER_VALIDATE_BOOLEAN)
                    ),
                    sale_discount_amount: @js(old('sale_discount_amount', '')),
                    clientName: @js(old('client_name', '')),
                    clientPhone: @js(old('client_phone', '')),
                    clients: @js($clients->map(fn ($client) => [
                        'name' => $client->name,
                        'phone' => $client->phone,
                    ])->values()),
                    clientPanelOpen: false,
                    isAdmin: @js(auth()->user()->isAdmin()),
                    allProductsFlat() {
                        const list = [];
                        for (const dept of this.catalog) {
                            for (const p of (dept.products || [])) {
                                list.push({
                                    id: p.id,
                                    department_id: dept.id,
                                    department_name: dept.name,
                                    label: p.label,
                                    unit_price: p.unit_price,
                                    stock_qty: Number(p.stock_qty) || 0,
                                });
                            }
                        }
                        return list;
                    },
                    qtyInCartForProduct(productId) {
                        if (!productId) return 0;
                        return this.rows
                            .filter(r => String(r.product_id) === String(productId))
                            .reduce((s, r) => s + (Number(r.quantity) || 0), 0);
                    },
                    stockRemainingForProduct(productId) {
                        const p = this.findProduct(productId);
                        if (!p) return 0;
                        const base = Number(p.stock_qty) || 0;
                        return Math.max(0, base - this.qtyInCartForProduct(productId));
                    },
                    get filteredProducts() {
                        const q = String(this.searchQuery || '').trim().toLowerCase();
                        const all = this.allProductsFlat();
                        if (q.length < 1) return all.slice(0, 12);
                        return all.filter(p => {
                            const t = (String(p.label) + ' ' + String(p.department_name)).toLowerCase();
                            return t.includes(q);
                        }).slice(0, 25);
                    },
                    findProduct(productId) {
                        if (!productId) return null;
                        for (const dept of this.catalog) {
                            const p = (dept.products || []).find(x => String(x.id) === String(productId));
                            if (p) return p;
                        }
                        return null;
                    },
                    rowProductLabel(row) {
                        if (row.product_label) return row.product_label;
                        const p = this.findProduct(row.product_id);
                        return p ? p.label : '—';
                    },
                    rowDepartmentName(row) {
                        if (row.department_name) return row.department_name;
                        const dept = this.catalog.find(d => String(d.id) === String(row.department_id));
                        return dept ? dept.name : '—';
                    },
                    lineAmount(row) {
                        const p = this.findProduct(row.product_id);
                        if (!p) return 0;
                        const unit = Number(p.unit_price) || 0;
                        const qty = Number(row.quantity) || 0;
                        return Math.round(unit * qty * 100) / 100;
                    },
                    subtotalAmount() {
                        return this.rows.reduce((s, row) => s + this.lineAmount(row), 0);
                    },
                    saleDiscountNumber() {
                        if (!this.apply_sale_discount) return 0;
                        const v = parseFloat(String(this.sale_discount_amount).replace(',', '.'));
                        return (Number.isFinite(v) && v > 0) ? v : 0;
                    },
                    grandTotal() {
                        return Math.max(0, Math.round((this.subtotalAmount() - this.saleDiscountNumber()) * 100) / 100);
                    },
                    displayTotalLabel() {
                        if (!this.apply_sale_discount || this.saleDiscountNumber() <= 0) return 'Total';
                        if (this.isAdmin) return 'Total';
                        return 'Total enregistré (remise en attente)';
                    },
                    displayTotalAmount() {
                        if (!this.apply_sale_discount || this.saleDiscountNumber() <= 0) return this.subtotalAmount();
                        if (this.isAdmin) return this.grandTotal();
                        return this.subtotalAmount();
                    },
                    formatUsd(n) {
                        return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    pickProduct(p) {
                        this.pendingProduct = p;
                        this.searchQuery = p.label;
                        this.pickerOpen = false;
                        this.addLineError = '';
                    },
                    clearPick() {
                        this.pendingProduct = null;
                        this.searchQuery = '';
                        this.pickerOpen = true;
                    },
                    addLine() {
                        this.addLineError = '';
                        if (!this.pendingProduct) {
                            this.addLineError = 'Sélectionnez un produit dans les résultats de recherche.';
                            return;
                        }
                        const qty = parseInt(String(this.addQuantity), 10);
                        if (!Number.isFinite(qty) || qty < 1) {
                            this.addLineError = 'Indiquez une quantité valide (minimum 1).';
                            return;
                        }
                        const avail = this.stockRemainingForProduct(this.pendingProduct.id);
                        if (qty > avail) {
                            this.addLineError = avail <= 0
                                ? 'Stock insuffisant sur ce point de vente pour ce produit.'
                                : ('Stock disponible : ' + avail + ' (déjà ' + this.qtyInCartForProduct(this.pendingProduct.id) + ' dans le panier).');
                            return;
                        }
                        this.rows.push({
                            _key: 'k' + Date.now() + '-' + Math.random().toString(16).slice(2),
                            department_id: String(this.pendingProduct.department_id),
                            product_id: String(this.pendingProduct.id),
                            product_label: this.pendingProduct.label,
                            department_name: this.pendingProduct.department_name,
                            quantity: qty,
                        });
                        this.pendingProduct = null;
                        this.searchQuery = '';
                        this.addQuantity = 1;
                    },
                    removeRow(index) {
                        this.rows.splice(index, 1);
                    },
                    get filteredClients() {
                        if (this.paymentType !== 'credit') return [];
                        const term = String(this.clientName || '').trim().toLowerCase();
                        if (!term) return this.clients.slice(0, 8);
                        return this.clients.filter(c => String(c.name || '').toLowerCase().includes(term)).slice(0, 8);
                    },
                }"
            >
                @csrf

                <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700">
                    Produits du département <strong>{{ $department->name }}</strong> uniquement, stock déduit sur <strong>{{ $pointOfSale->name }}</strong>. Recherchez un <strong>produit</strong>, indiquez la <strong>quantité</strong>, puis <strong>Ajouter</strong>.
                    Les lignes apparaissent dans le tableau ; vous pouvez en ajouter plusieurs.
                </div>

                @if (count($saleCatalog) === 0 || ($saleCatalog[0]['products'] ?? []) === [])
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Aucun produit dans ce département pour cette branche.
                    </div>
                @endif

                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-neutral-900">Lignes de produits</h2>
                    <p class="mt-1 text-sm text-neutral-600">Recherche, quantité, puis ajout au panier.</p>

                    <div class="mt-4 space-y-3">
                        <div class="relative" @click.outside="pickerOpen = false">
                            <label for="sale-product-search" class="mb-1.5 block text-xs font-semibold text-neutral-700">Rechercher un produit</label>
                            <input
                                id="sale-product-search"
                                type="search"
                                autocomplete="off"
                                x-model="searchQuery"
                                @focus="pickerOpen = true"
                                @input="pickerOpen = true; if (pendingProduct && searchQuery !== pendingProduct.label) pendingProduct = null"
                                placeholder="Nom, prix affiché, département…"
                                class="block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary"
                            />
                            <div
                                x-show="pickerOpen && filteredProducts.length > 0 && catalog.length > 0"
                                x-cloak
                                class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-neutral-200 bg-white py-1 shadow-lg"
                            >
                                <template x-for="p in filteredProducts" :key="String(p.id) + '-' + String(p.department_id)">
                                    <button
                                        type="button"
                                        class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-neutral-50"
                                        @click="pickProduct(p)"
                                    >
                                        <span class="min-w-0 flex flex-col items-start">
                                            <span class="font-medium text-neutral-900" x-text="p.label"></span>
                                            <span class="text-xs text-neutral-500" x-text="p.department_name"></span>
                                        </span>
                                        <span
                                            class="shrink-0 rounded-md border px-2 py-0.5 text-xs font-semibold tabular-nums"
                                            :class="stockRemainingForProduct(p.id) <= 0 ? 'border-red-200 bg-red-50 text-red-800' : 'border-neutral-200 bg-neutral-50 text-neutral-700'"
                                            x-text="'Stock : ' + stockRemainingForProduct(p.id)"
                                        ></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div x-show="pendingProduct" x-cloak class="rounded-lg border border-primary/25 bg-primary/5 px-3 py-2 text-sm text-neutral-800">
                            <span class="text-neutral-600">Sélection :</span>
                            <strong class="ml-1" x-text="pendingProduct.label"></strong>
                            <span class="ml-2 text-xs font-medium text-neutral-600">
                                — <span x-text="'disponible : ' + stockRemainingForProduct(pendingProduct.id)"></span>
                            </span>
                            <button type="button" class="ml-2 text-xs font-semibold text-primary hover:underline" @click="clearPick()">Changer</button>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                            <div class="w-full sm:w-28">
                                <label for="sale-add-qty" class="mb-1.5 block text-xs font-semibold text-neutral-700">Quantité</label>
                                <input
                                    id="sale-add-qty"
                                    type="number"
                                    min="1"
                                    x-model.number="addQuantity"
                                    class="block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary"
                                />
                            </div>
                            <button
                                type="button"
                                class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-95 sm:w-auto"
                                @click="addLine()"
                            >
                                Ajouter
                            </button>
                        </div>

                        <p x-show="addLineError" x-text="addLineError" class="text-sm font-medium text-red-600" x-cloak></p>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-xl border border-neutral-200">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                <tr>
                                    <th class="px-3 py-3">Produit</th>
                                    <th class="px-3 py-3">Département</th>
                                    <th class="px-3 py-3">Point de vente</th>
                                    <th class="px-3 py-3 text-right">Qté</th>
                                    <th class="px-3 py-3 text-right">Montant</th>
                                    <th class="px-3 py-3 text-right w-24"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <template x-for="(row, index) in rows" :key="row._key">
                                    <tr class="bg-white">
                                        <td class="px-3 py-3 font-medium text-neutral-900" x-text="rowProductLabel(row)"></td>
                                        <td class="px-3 py-3 text-neutral-600" x-text="rowDepartmentName(row)"></td>
                                        <td class="px-3 py-3 text-neutral-600" x-text="posName"></td>
                                        <td class="px-3 py-3 text-right tabular-nums" x-text="row.quantity"></td>
                                        <td class="px-3 py-3 text-right tabular-nums font-medium text-neutral-900" x-text="formatUsd(lineAmount(row))"></td>
                                        <td class="px-3 py-3 text-right">
                                            <button
                                                type="button"
                                                class="text-xs font-semibold text-red-600 hover:text-red-800"
                                                @click="removeRow(index)"
                                            >
                                                Retirer
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p x-show="rows.length === 0" class="px-4 py-8 text-center text-sm text-neutral-500" x-cloak>Aucune ligne — recherchez un produit et cliquez sur <strong>Ajouter</strong>.</p>
                    </div>

                    <template x-for="(row, index) in rows" :key="'hid-' + row._key">
                        <div class="hidden" aria-hidden="true">
                            <input type="hidden" :name="`items[${index}][department_id]`" :value="row.department_id" />
                            <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id" />
                            <input type="hidden" :name="`items[${index}][quantity]`" :value="row.quantity" />
                        </div>
                    </template>

                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-neutral-900">Totaux et remise sur la vente</h2>
                    <p class="mt-1 text-sm text-neutral-600">La remise s’applique à l’ensemble de la vente (pas ligne par ligne).</p>
                    <div class="mt-4 space-y-3 rounded-xl border border-neutral-100 bg-neutral-50/80 px-4 py-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-neutral-700">Sous-total</span>
                            <span class="tabular-nums font-semibold text-neutral-900" x-text="formatUsd(subtotalAmount())"></span>
                        </div>
                        <div class="flex items-start gap-3 border-t border-neutral-200/80 pt-3">
                            <input
                                id="apply_sale_discount"
                                type="checkbox"
                                name="apply_sale_discount"
                                value="1"
                                class="mt-1 rounded border-neutral-300 text-primary focus:ring-primary"
                                x-model="apply_sale_discount"
                            />
                            <div class="min-w-0 flex-1">
                                <label for="apply_sale_discount" class="text-sm font-semibold text-neutral-900">Appliquer une remise</label>
                                <div class="mt-2" x-show="apply_sale_discount" x-cloak>
                                    <label for="sale_discount_amount" class="mb-1 block text-xs font-medium text-neutral-600">Montant de la remise (USD)</label>
                                    <input
                                        id="sale_discount_amount"
                                        name="sale_discount_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="block max-w-xs rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary"
                                        x-model="sale_discount_amount"
                                    />
                                    <template x-if="!isAdmin">
                                        <p class="mt-2 text-xs text-amber-900">La remise ne sera effective sur le total qu’après approbation d’un administrateur. Jusqu’à ce moment, le montant enregistré pour la vente reste le sous-total.</p>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t border-neutral-200/80 pt-3 text-sm" x-show="apply_sale_discount && saleDiscountNumber() > 0" x-cloak>
                            <span class="text-neutral-600">Montant si la remise est approuvée</span>
                            <span class="tabular-nums font-semibold text-primary" x-text="formatUsd(grandTotal())"></span>
                        </div>
                        <div class="flex items-center justify-between border-t border-neutral-200/80 pt-3 text-sm font-semibold text-neutral-900">
                            <span x-text="displayTotalLabel()"></span>
                            <span class="tabular-nums text-primary" x-text="formatUsd(displayTotalAmount())"></span>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('apply_sale_discount')" class="mt-2" />
                    <x-input-error :messages="$errors->get('sale_discount_amount')" class="mt-2" />
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-neutral-900">Paiement</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <x-input-label for="payment_type" value="Mode de paiement" class="text-sm font-semibold text-neutral-800" />
                            <select id="payment_type" name="payment_type" x-model="paymentType" class="mt-2 block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary" required>
                                <option value="cash" @selected(old('payment_type', 'cash') === 'cash')>Cash</option>
                                <option value="credit" @selected(old('payment_type') === 'credit')>Crédit</option>
                            </select>
                            <x-input-error :messages="$errors->get('payment_type')" class="mt-2" />
                        </div>

                        <div>
                            <p class="text-xs text-neutral-500">
                                <strong>Cash :</strong> nom et téléphone optionnels, enregistrés sur la vente (sans fiche client).
                                <strong>Crédit :</strong> nom obligatoire, client lié au module clients pour le suivi des dettes.
                            </p>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <x-input-label for="client_name" value="Nom du client" class="text-sm font-semibold text-neutral-800" />
                                    <div class="relative mt-2">
                                        <input
                                            id="client_name"
                                            name="client_name"
                                            type="text"
                                            x-model="clientName"
                                            x-bind:required="paymentType === 'credit'"
                                            x-on:focus="if (paymentType === 'credit') clientPanelOpen = true"
                                            x-on:input="if (paymentType === 'credit') clientPanelOpen = true"
                                            x-on:keydown.escape="clientPanelOpen = false"
                                            x-on:click.outside="clientPanelOpen = false"
                                            placeholder="Ex. Jean Dupont (laisser vide au comptant si inconnu)"
                                            class="block w-full rounded-xl border-neutral-200 pr-10 shadow-sm focus:border-primary focus:ring-primary"
                                        />
                                        <button type="button" class="absolute inset-y-0 right-3 my-auto text-neutral-500" x-show="paymentType === 'credit'" x-on:click="clientPanelOpen = !clientPanelOpen" aria-label="Afficher les suggestions">
                                            ▼
                                        </button>
                                        <div
                                            x-show="paymentType === 'credit' && clientPanelOpen && filteredClients.length"
                                            x-cloak
                                            class="absolute z-20 mt-2 max-h-56 w-full overflow-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-lg"
                                        >
                                            <template x-for="client in filteredClients" :key="`${client.name}-${client.phone ?? ''}`">
                                                <button
                                                    type="button"
                                                    class="block w-full rounded-lg px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-100"
                                                    x-text="client.name"
                                                    x-on:click="clientName = client.name; clientPhone = client.phone ?? ''; clientPanelOpen = false"
                                                ></button>
                                            </template>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-neutral-500" x-show="paymentType === 'credit'" x-cloak>Suggestions à partir des clients déjà enregistrés.</p>
                                    <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="client_phone" value="Téléphone" class="text-sm font-semibold text-neutral-800" />
                                    <input
                                        id="client_phone"
                                        name="client_phone"
                                        type="text"
                                        x-model="clientPhone"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        placeholder="Optionnel"
                                        class="mt-2 block w-full rounded-xl border-neutral-200 shadow-sm focus:border-primary focus:ring-primary"
                                    />
                                    <x-input-error :messages="$errors->get('client_phone')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-neutral-100 pt-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}" class="inline-flex items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        x-bind:disabled="rows.length === 0"
                        @if (count($saleCatalog) === 0 || ($saleCatalog[0]['products'] ?? []) === []) disabled @endif
                    >
                        Valider la vente
                    </button>
                </div>
            </form>
        </section>

        <aside class="h-fit rounded-2xl border border-neutral-200 bg-gradient-to-b from-white to-neutral-50 p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Contexte</h2>
            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Branche</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $branch->name }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Point de vente</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $pointOfSale->name }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Département</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $department->name }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Produits (ce département)</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $productsCount }}</p>
                </div>
            </div>
        </aside>
        </div>
    </x-caisse-flow>
</x-app-layout>
