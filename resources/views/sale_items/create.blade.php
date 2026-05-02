<x-app-layout>
    <x-slot name="header">Nouvelle vente — Session #{{ $salesSession->id }}</x-slot>

    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Point de vente</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">Enregistrer une vente</h1>
            <p class="mt-2 text-sm text-neutral-600">
                Session #{{ $salesSession->id }} · Branche {{ $salesSession->branch->name }}
            </p>
        </div>
        <a href="{{ route('sales-sessions.show', $salesSession) }}" class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:border-primary/30 hover:text-primary">
            <span aria-hidden="true">←</span>
            Retour à la session
        </a>
    </div>

    @if ($errors->has('sale'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('sale') }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form
                action="{{ route('sale-items.store', $salesSession) }}"
                method="POST"
                class="space-y-6"
                x-data="{
                    paymentType: @js(old('payment_type', 'cash')),
                    catalog: @js($saleCatalog),
                    rows: @js($saleLineRows),
                    apply_sale_discount: @js(filter_var(old('apply_sale_discount'), FILTER_VALIDATE_BOOLEAN)),
                    sale_discount_amount: @js(old('sale_discount_amount', '')),
                    clientName: @js(old('client_name', '')),
                    clientPhone: @js(old('client_phone', '')),
                    clients: @js($clients->map(fn ($client) => [
                        'name' => $client->name,
                        'phone' => $client->phone,
                    ])->values()),
                    clientPanelOpen: false,
                    isAdmin: @js((bool) auth()->user()->is_admin),
                    productsForDept(deptId) {
                        if (deptId === '' || deptId === null || deptId === undefined) return [];
                        const d = this.catalog.find(dept => String(dept.id) === String(deptId));
                        return d && d.products ? d.products : [];
                    },
                    findProduct(productId) {
                        if (!productId) return null;
                        for (const dept of this.catalog) {
                            const p = (dept.products || []).find(x => String(x.id) === String(productId));
                            if (p) return p;
                        }
                        return null;
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
                    addRow() {
                        this.rows.push({ department_id: '', product_id: '', location_id: '', quantity: 1 });
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
                    Choisissez d’abord un <strong>département</strong>, puis un <strong>produit</strong> de ce département — la liste reste courte à la caisse.
                    Le stock sera déduit de l’emplacement choisi.
                </div>

                @if (count($saleCatalog) === 0)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Aucun département avec des produits accessibles pour cette branche. Ajoutez des produits ou des stocks liés à la branche avant de vendre.
                    </div>
                @endif

                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-neutral-900">Lignes de produits</h2>
                        <button type="button" class="rounded-md border border-neutral-300 px-3 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-50" @click="addRow()">Ajouter une ligne</button>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-4 shadow-sm ring-1 ring-neutral-100">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-400" x-text="'Ligne ' + (index + 1)"></p>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-12 xl:gap-x-3 xl:gap-y-0">
                                    <div class="sm:col-span-2 xl:col-span-2">
                                        <label :for="'sale-line-' + index + '-department'" class="mb-1.5 block text-xs font-semibold text-neutral-700">Département</label>
                                        <select
                                            :id="'sale-line-' + index + '-department'"
                                            x-model="row.department_id"
                                            @change="row.product_id = ''"
                                            class="block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary"
                                        >
                                            <option value="">Choisir…</option>
                                            <template x-for="dept in catalog" :key="dept.id">
                                                <option :value="String(dept.id)" x-text="dept.name"></option>
                                            </template>
                                        </select>
                                        <p class="mt-1 text-[11px] text-neutral-500">Filtre les produits de la ligne.</p>
                                    </div>
                                    <div class="sm:col-span-2 xl:col-span-4">
                                        <label :for="'sale-line-' + index + '-product'" class="mb-1.5 block text-xs font-semibold text-neutral-700">Produit</label>
                                        <select
                                            :id="'sale-line-' + index + '-product'"
                                            :name="`items[${index}][product_id]`"
                                            x-model="row.product_id"
                                            class="block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-500"
                                            x-bind:disabled="!row.department_id"
                                            required
                                        >
                                            <option value="" x-text="row.department_id ? 'Choisir un produit…' : '— Choisir un département d’abord —'"></option>
                                            <template x-for="p in productsForDept(row.department_id)" :key="p.id">
                                                <option :value="String(p.id)" x-text="p.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2 xl:col-span-2">
                                        <label :for="'sale-line-' + index + '-location'" class="mb-1.5 block text-xs font-semibold text-neutral-700">Emplacement</label>
                                        <select
                                            :id="'sale-line-' + index + '-location'"
                                            :name="`items[${index}][location_id]`"
                                            x-model="row.location_id"
                                            class="block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary"
                                            required
                                        >
                                            <option value="">Choisir un emplacement…</option>
                                            @foreach ($locations as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="xl:col-span-2">
                                        <label :for="'sale-line-' + index + '-qty'" class="mb-1.5 block text-xs font-semibold text-neutral-700">Quantité</label>
                                        <input
                                            :id="'sale-line-' + index + '-qty'"
                                            :name="`items[${index}][quantity]`"
                                            x-model="row.quantity"
                                            type="number"
                                            min="1"
                                            class="block w-full rounded-xl border-neutral-200 bg-white shadow-sm focus:border-primary focus:ring-primary"
                                            required
                                        />
                                    </div>
                                    <div class="flex flex-col justify-end sm:col-span-2 xl:col-span-2">
                                        <span class="mb-1.5 block text-xs font-semibold text-neutral-700 xl:sr-only">Retirer la ligne</span>
                                        <button
                                            type="button"
                                            class="rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                                            @click="rows.splice(index, 1)"
                                            x-show="rows.length > 1"
                                        >
                                            Retirer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
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
                            <input type="hidden" name="apply_sale_discount" value="0" />
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
                                Au comptant, ces champs sont optionnels. En crédit, le nom du client est obligatoire.
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
                    <a href="{{ route('sales-sessions.show', $salesSession) }}" class="inline-flex items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2" @if (count($saleCatalog) === 0) disabled @endif>
                        Valider la vente
                    </button>
                </div>
            </form>
        </section>

        <aside class="h-fit rounded-2xl border border-neutral-200 bg-gradient-to-b from-white to-neutral-50 p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Session active</h2>
            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Branche</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $salesSession->branch->name }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Session</p>
                    <p class="mt-1 font-semibold text-neutral-900">#{{ $salesSession->id }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Produits disponibles</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $productsCount }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Emplacements</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $locations->count() }}</p>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
