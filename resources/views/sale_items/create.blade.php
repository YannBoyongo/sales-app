<x-app-layout>
    <x-slot name="header">Nouvelle vente — {{ $posTerminal->name }} — {{ $department->name }}</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Caisse</p>
                    <h1 class="app-page-title">Nouvelle vente</h1>
                    <p class="app-page-desc">
                        {{ $department->name }} · {{ $pointOfSale->name }} · {{ $branch->name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('sales.choose-department', [$branch, $posTerminal]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:border-primary/30 hover:text-primary">
                        Autre département
                    </a>
                    <a href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:text-primary">
                        Retour caisse
                    </a>
                </div>
            </div>
        </x-slot>

        <x-slot name="stepper">
            <x-flow-sale-stepper :step="4" :total-steps="4" />
        </x-slot>

        @if ($errors->has('sale'))
            <div class="mb-6 app-alert-danger">{{ $errors->first('sale') }}</div>
        @endif

        @php
            $hasProducts = count($saleCatalog) > 0 && ($saleCatalog[0]['products'] ?? []) !== [];
        @endphp

        <form
            action="{{ route('sales.store', [$branch, $posTerminal, $department]) }}"
            method="POST"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(380px,480px)] lg:items-start"
            x-data="posSaleForm({
                customerType: @js($saleEffectiveCustomerType),
                canChooseDealer: @js($canChooseDealerCustomer),
                catalog: @js($saleCatalog),
                rows: @js($saleLineRows),
                posName: @js($pointOfSale->name),
                apply_sale_discount: @js(filter_var(old('apply_sale_discount'), FILTER_VALIDATE_BOOLEAN)),
                sale_discount_amount: @js(old('sale_discount_amount', '')),
                clientName: @js(old('client_name', '')),
                clientPhone: @js(old('client_phone', '')),
                clients: @js($clients->map(fn ($c) => [
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'caution_balance' => bcsub((string) ($c->caution_total ?? '0'), (string) ($c->caution_used ?? '0'), 2),
                ])->values()),
                isAdmin: @js(auth()->user()->isAdmin()),
                amountPaid: @js(old('amount_paid', '0')),
                paymentType: @js(old('payment_type', $saleEffectiveCustomerType === 'dealer' ? 'credit' : 'cash')),
                allowLineDiscount: @js(filter_var(old('allow_line_discount'), FILTER_VALIDATE_BOOLEAN)),
            })"
            x-effect="syncPaymentTypeByCustomer(); syncDealerDiscountMode()"
            @submit="guardSubmit($event)"
        >
            @csrf

            {{-- Colonne principale : étapes 1 et 2 --}}
            <div class="space-y-6">
                {{-- Étape 1 : type de client --}}
                <section class="app-panel app-panel-body">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">1</span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-neutral-900">Type de vente</h2>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2 @if ($canChooseDealerCustomer) sm:grid-cols-2 @endif">
                        <label
                            class="relative cursor-pointer rounded-lg border-2 p-3 transition"
                            :class="customerType === 'walkin' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-neutral-200 hover:border-neutral-300'"
                        >
                            <input type="radio" name="customer_type" value="walkin" x-model="customerType" class="sr-only">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-base" aria-hidden="true">💵</span>
                                <div class="min-w-0">
                                    <span class="text-sm font-semibold text-neutral-900">Client comptant</span>
                                    <p class="truncate text-[11px] text-neutral-500">Prix catalogue · paiement immédiat</p>
                                </div>
                            </div>
                        </label>

                        @if ($canChooseDealerCustomer)
                            <label
                                class="relative cursor-pointer rounded-lg border-2 p-3 transition"
                                :class="customerType === 'dealer' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-neutral-200 hover:border-neutral-300'"
                            >
                                <input type="radio" name="customer_type" value="dealer" x-model="customerType" class="sr-only">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-amber-50 text-base" aria-hidden="true">🏪</span>
                                    <div class="min-w-0">
                                        <span class="text-sm font-semibold text-neutral-900">Revendeur/Client</span>
                                        <p class="truncate text-[11px] text-neutral-500">Crédit, caution ou remise</p>
                                    </div>
                                </div>
                            </label>
                        @endif
                    </div>
                    <x-input-error :messages="$errors->get('customer_type')" class="mt-2" />

                    <div x-show="customerType === 'dealer'" x-cloak class="mt-3 space-y-3 rounded-lg border border-amber-200/80 bg-amber-50/50 p-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="relative sm:col-span-2">
                                <x-input-label for="client_name" value="Nom du revendeur" class="text-xs font-semibold text-neutral-800" />
                                <input
                                    id="client_name"
                                    name="client_name"
                                    type="text"
                                    x-model="clientName"
                                    x-bind:required="customerType === 'dealer'"
                                    x-on:focus="clientPanelOpen = true"
                                    x-on:input="clientPanelOpen = true"
                                    placeholder="Rechercher ou saisir un nom"
                                    class="mt-1 block w-full rounded-lg border-neutral-200 bg-white py-2 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                />
                                <div
                                    x-show="clientPanelOpen && filteredClients.length"
                                    x-cloak
                                    @click.outside="clientPanelOpen = false"
                                    class="absolute z-20 mt-1 max-h-40 w-full overflow-auto rounded-lg border border-neutral-200 bg-white py-1 shadow-lg"
                                >
                                    <template x-for="client in filteredClients" :key="client.name + (client.phone ?? '')">
                                        <button
                                            type="button"
                                            class="block w-full px-3 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-50"
                                            x-text="client.name"
                                            @click="clientName = client.name; clientPhone = client.phone ?? ''; clientPanelOpen = false"
                                        ></button>
                                    </template>
                                </div>
                                <x-input-error :messages="$errors->get('client_name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="client_phone" value="Téléphone" class="text-xs text-neutral-700" />
                                <input id="client_phone" name="client_phone" type="text" x-model="clientPhone" placeholder="Optionnel" class="mt-1 block w-full rounded-lg border-neutral-200 bg-white py-2 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                            </div>
                            <div
                                x-show="selectedDealerClient"
                                x-cloak
                                class="flex items-center justify-between rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm"
                            >
                                <span class="text-xs font-semibold text-sky-800/80">Caution</span>
                                <span class="font-semibold text-sky-900 tabular-nums" x-text="formatUsd(selectedDealerClient?.caution_balance ?? 0)"></span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Étape 2 : produits --}}
                <section class="app-panel app-panel-body sm:p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">2</span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-neutral-900">Articles</h2>
                            <p class="mt-0.5 text-sm text-neutral-600">
                                Recherchez, ajoutez au panier
                                <span x-show="customerType === 'dealer'" x-cloak>— cochez <strong>Remise</strong> dans le récapitulatif pour ajuster le prix unitaire</span>.
                            </p>
                        </div>
                    </div>

                    @unless ($hasProducts)
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                            Aucun produit disponible dans ce département.
                        </div>
                    @endunless

                    @if ($hasProducts)
                        <div class="mt-5 rounded-xl border border-neutral-100 bg-neutral-50/80 p-4">
                            <label for="sale-product-search" class="text-xs font-semibold uppercase tracking-wide text-neutral-600">Ajouter un produit</label>
                            <div class="relative mt-2" @click.outside="pickerOpen = false">
                                <input
                                    id="sale-product-search"
                                    type="search"
                                    autocomplete="off"
                                    x-model="searchQuery"
                                    @focus="pickerOpen = true"
                                    @input="pickerOpen = true; if (pendingProduct && searchQuery !== pendingProduct.label) pendingProduct = null"
                                    placeholder="Tapez le nom du produit…"
                                    class="block w-full rounded-xl border-neutral-200 bg-white py-2.5 pl-10 shadow-sm focus:border-primary focus:ring-primary"
                                />
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <div
                                    x-show="pickerOpen && filteredProducts.length"
                                    x-cloak
                                    class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-neutral-200 bg-white py-1 shadow-xl"
                                >
                                    <template x-for="p in filteredProducts" :key="p.id">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left text-sm hover:bg-neutral-50"
                                            @click="pickProduct(p)"
                                        >
                                            <span>
                                                <span class="font-medium text-neutral-900" x-text="p.name || p.label"></span>
                                                <span class="mt-0.5 block text-xs text-neutral-500" x-text="formatUsd(p.unit_price) + ' · stock ' + stockRemainingForProduct(p.id)"></span>
                                            </span>
                                            <span
                                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                                                :class="stockRemainingForProduct(p.id) <= 0 ? 'bg-red-100 text-red-800' : 'bg-neutral-100 text-neutral-700'"
                                                x-text="stockRemainingForProduct(p.id)"
                                            ></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="pendingProduct" x-cloak class="mt-3 flex flex-wrap items-end gap-3 rounded-lg border border-primary/30 bg-white p-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs text-neutral-500">Sélection</p>
                                    <p class="font-semibold text-neutral-900" x-text="pendingProduct?.name || pendingProduct?.label"></p>
                                    <p class="text-xs text-neutral-600">
                                        Catalogue : <span x-text="formatUsd(pendingProduct?.unit_price)"></span>
                                        · dispo <span x-text="stockRemainingForProduct(pendingProduct?.id)"></span>
                                    </p>
                                </div>
                                <div class="w-24">
                                    <label class="text-xs font-semibold text-neutral-600">Qté</label>
                                    <input type="number" min="1" x-model.number="addQuantity" class="mt-1 block w-full rounded-lg border-neutral-200 text-sm focus:border-primary focus:ring-primary" />
                                </div>
                                <button type="button" @click="addLine()" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                                    Ajouter
                                </button>
                                <button type="button" @click="clearPick()" class="text-xs font-medium text-neutral-500 hover:text-primary">Annuler</button>
                            </div>
                            <p x-show="addLineError" x-text="addLineError" class="mt-2 text-sm font-medium text-red-600" x-cloak></p>
                        </div>

                        {{-- Panier --}}
                        <div class="mt-5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-neutral-800">
                                    Panier
                                    <span class="ml-1 rounded-full bg-neutral-100 px-2 py-0.5 text-xs tabular-nums text-neutral-600" x-text="rows.length"></span>
                                </h3>
                                <span x-show="customerType === 'dealer' && allowLineDiscount && rows.length" x-cloak class="text-xs text-amber-800">Prix négociables</span>
                            </div>

                            <div x-show="rows.length === 0" class="mt-3 rounded-xl border border-dashed border-neutral-200 px-4 py-10 text-center text-sm text-neutral-500">
                                Le panier est vide. Recherchez un produit ci-dessus.
                            </div>

                            <ul class="mt-3 space-y-3">
                                <template x-for="(row, index) in rows" :key="row._key">
                                    <li class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="font-semibold text-neutral-900" x-text="rowProductName(row)"></p>
                                                <p class="mt-0.5 text-xs text-neutral-500" x-text="posName"></p>
                                            </div>
                                            <button type="button" @click="removeRow(index)" class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-800">Retirer</button>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            <div>
                                                <label class="text-xs font-semibold text-neutral-600">Quantité</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    x-model.number="row.quantity"
                                                    class="mt-1 block w-full rounded-lg border-neutral-200 text-sm tabular-nums focus:border-primary focus:ring-primary"
                                                />
                                            </div>
                                            <div x-show="customerType === 'dealer' && allowLineDiscount" x-cloak class="col-span-2 sm:col-span-1">
                                                <label class="text-xs font-semibold text-neutral-600">Prix unit. (USD)</label>
                                                <div class="mt-1 flex gap-1">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        x-model.number="row.unit_price"
                                                        class="block w-full rounded-lg border-amber-200 bg-amber-50/50 text-sm tabular-nums focus:border-primary focus:ring-primary"
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    x-show="priceDiffersFromCatalog(row)"
                                                    @click="resetRowPrice(row)"
                                                    class="mt-1 text-[10px] font-medium text-primary hover:underline"
                                                >
                                                    Rétablir <span x-text="formatUsd(row.catalog_unit_price)"></span>
                                                </button>
                                            </div>
                                            <div x-show="customerType === 'walkin' || (customerType === 'dealer' && !allowLineDiscount)" x-cloak>
                                                <label class="text-xs font-semibold text-neutral-600">Prix unit.</label>
                                                <p class="mt-2 text-sm tabular-nums text-neutral-800" x-text="formatUsd(catalogUnitPrice(row))"></p>
                                            </div>
                                            <div class="text-right">
                                                <label class="text-xs font-semibold text-neutral-600">Ligne</label>
                                                <p class="mt-2 text-sm font-bold tabular-nums text-primary" x-text="formatUsd(lineAmount(row))"></p>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>

                            <template x-for="(row, index) in rows" :key="'hid-' + row._key">
                                <div class="hidden" aria-hidden="true">
                                    <input type="hidden" :name="`items[${index}][department_id]`" :value="row.department_id" />
                                    <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id" />
                                    <input type="hidden" :name="`items[${index}][quantity]`" :value="row.quantity" />
                                    <template x-if="customerType === 'dealer'">
                                        <input type="hidden" :name="`items[${index}][unit_price]`" :value="rowUnitPrice(row)" />
                                    </template>
                                </div>
                            </template>
                            <x-input-error :messages="$errors->get('items')" class="mt-2" />
                            <x-input-error :messages="$errors->get('items.*.unit_price')" class="mt-2" />
                        </div>
                    @endif
                </section>
            </div>

            {{-- Panneau récapitulatif (sticky) --}}
            <aside class="lg:sticky lg:top-6 lg:self-stretch">
                <div class="app-panel app-panel-body flex h-full flex-col space-y-5 sm:p-6">
                    <div class="flex items-start gap-3 border-b border-neutral-100 pb-5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-800 text-sm font-bold text-white">3</span>
                        <div>
                            <h2 class="text-lg font-semibold text-neutral-900">Récapitulatif</h2>
                            <p class="mt-0.5 text-sm text-neutral-500">Vérifiez puis validez la vente</p>
                        </div>
                    </div>

                    <div class="min-h-[12rem] flex-1 border-b border-neutral-100 pb-5">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Articles</p>
                        <div x-show="rows.length === 0" class="flex min-h-[10rem] items-center justify-center rounded-xl border border-dashed border-neutral-200 px-4 py-6 text-center text-sm text-neutral-500">
                            Aucun article ajouté.
                        </div>
                        <div x-show="rows.length > 0" x-cloak class="max-h-64 overflow-auto rounded-xl border border-neutral-100">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                    <tr>
                                        <th class="px-3 py-2.5">Produit</th>
                                        <th class="px-3 py-2.5 text-right">Qté</th>
                                        <th class="px-3 py-2.5 text-right">P.U.</th>
                                        <th class="px-3 py-2.5 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-for="row in rows" :key="'recap-' + row._key">
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-neutral-900" x-text="rowProductName(row)"></td>
                                            <td class="px-2 py-2 text-right">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    x-model.number="row.quantity"
                                                    class="ml-auto block w-16 rounded-md border-neutral-200 py-1 text-right text-sm tabular-nums focus:border-primary focus:ring-primary"
                                                />
                                            </td>
                                            <td class="px-2 py-2 text-right">
                                                <input
                                                    x-show="customerType === 'dealer' && allowLineDiscount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    x-model.number="row.unit_price"
                                                    class="ml-auto block w-24 rounded-md border-amber-200 bg-amber-50/50 py-1 text-right text-sm tabular-nums focus:border-primary focus:ring-primary"
                                                />
                                                <span
                                                    x-show="customerType !== 'dealer' || !allowLineDiscount"
                                                    class="tabular-nums text-neutral-700"
                                                    x-text="formatUsd(rowUnitPrice(row))"
                                                ></span>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-neutral-900" x-text="formatUsd(lineAmount(row))"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-neutral-600">Lignes</dt>
                            <dd class="font-semibold tabular-nums text-neutral-900" x-text="rows.length"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-600">Sous-total</dt>
                            <dd class="font-semibold tabular-nums text-neutral-900" x-text="formatUsd(subtotalAmount())"></dd>
                        </div>
                    </dl>

                    <div x-show="customerType === 'dealer'" x-cloak class="border-t border-neutral-100 pt-3">
                        <label class="flex cursor-pointer items-start gap-2">
                            <input
                                type="checkbox"
                                name="allow_line_discount"
                                value="1"
                                x-model="allowLineDiscount"
                                @change="if (!allowLineDiscount) resetAllRowPricesToCatalog()"
                                class="mt-0.5 rounded border-neutral-300 text-primary focus:ring-primary"
                            />
                            <span>
                                <span class="text-sm font-medium text-neutral-800">Remise</span>
                                <span class="mt-0.5 block text-xs text-neutral-600">Cocher pour modifier le prix unitaire dans le tableau.</span>
                            </span>
                        </label>
                        <p x-show="allowLineDiscount && !isAdmin" x-cloak class="mt-2 text-[11px] leading-relaxed text-amber-900">
                            La vente restera en attente jusqu’à approbation d’un administrateur si les prix sont inférieurs au catalogue.
                        </p>
                    </div>

                    {{-- Remise globale — désactivée temporairement (réactiver sur demande)
                    <div class="border-t border-neutral-100 pt-3">
                        <label class="flex cursor-pointer items-start gap-2">
                            <input type="checkbox" name="apply_sale_discount" value="1" class="mt-0.5 rounded border-neutral-300 text-primary focus:ring-primary" x-model="apply_sale_discount" />
                            <span class="text-sm font-medium text-neutral-800">Remise globale</span>
                        </label>
                        <div x-show="apply_sale_discount" x-cloak class="mt-2">
                            <input
                                name="sale_discount_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Montant USD"
                                class="block w-full rounded-lg border-neutral-200 text-sm focus:border-primary focus:ring-primary"
                                x-model="sale_discount_amount"
                            />
                            <p x-show="!isAdmin" class="mt-1 text-[11px] text-amber-900">Approbation admin requise pour appliquer la remise.</p>
                        </div>
                        <x-input-error :messages="$errors->get('sale_discount_amount')" class="mt-1" />
                    </div>
                    --}}

                    <div class="rounded-xl bg-neutral-50 px-4 py-4">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-semibold text-neutral-800" x-text="displayTotalLabel()"></span>
                            <span class="text-2xl font-bold tabular-nums text-primary" x-text="formatUsd(displayTotalAmount())"></span>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-neutral-100 pt-3">
                        <div>
                            <label for="payment_type" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Type de paiement</label>
                            <select
                                id="payment_type"
                                name="payment_type"
                                x-model="paymentType"
                                class="mt-1 block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary"
                            >
                                <template x-for="option in availablePaymentTypes()" :key="option.value">
                                    <option :value="option.value" x-text="option.label"></option>
                                </template>
                            </select>
                            <p x-show="paymentType === 'caution'" x-cloak class="mt-1 text-[11px] text-amber-900">
                                Vente réglée par caution: ce montant ne sera pas compté dans le total encaissé à la clôture du shift.
                            </p>
                            <x-input-error :messages="$errors->get('payment_type')" class="mt-1" />
                        </div>
                    </div>

                    <div x-show="customerType === 'dealer' && paymentType === 'credit'" x-cloak class="space-y-3 border-t border-neutral-100 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Encaissement revendeur</p>
                        <div>
                            <label for="amount_paid" class="text-xs font-semibold text-neutral-700">Montant payé maintenant</label>
                            <input id="amount_paid" name="amount_paid" type="number" step="0.01" min="0" x-model="amountPaid" class="mt-1 block w-full rounded-xl border-neutral-200 text-sm tabular-nums focus:border-primary focus:ring-primary" />
                            <x-input-error :messages="$errors->get('amount_paid')" class="mt-1" />
                        </div>
                        <div class="flex justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                            <span class="font-medium text-amber-950">Solde (dette)</span>
                            <span class="font-bold tabular-nums text-amber-950" x-text="formatUsd(dealerBalance())"></span>
                        </div>
                    </div>
                    <input type="hidden" name="balance" :value="dealerBalance().toFixed(2)" />

                    <div class="flex flex-col gap-2 pt-2">
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="submitting || rows.length === 0 || (customerType === 'dealer' && !String(clientName || '').trim())"
                            @unless ($hasProducts) disabled @endunless
                        >
                            <span x-show="!submitting">Valider la vente</span>
                            <span x-show="submitting" x-cloak>Validation en cours…</span>
                        </button>
                        <a href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-neutral-200 py-2.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                            Annuler
                        </a>
                    </div>

                    <p class="text-center text-[11px] text-neutral-400">
                        Stock déduit sur {{ $pointOfSale->name }}
                    </p>
                </div>

                <div class="mt-4 hidden rounded-xl border border-neutral-200 bg-neutral-50/80 px-4 py-3 text-xs text-neutral-600 lg:block">
                    <p><strong>{{ $productsCount }}</strong> produits dans {{ $department->name }}</p>
                    <p class="mt-1">Terminal : {{ $posTerminal->name }}</p>
                </div>
            </aside>
        </form>
    </x-caisse-flow>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('posSaleForm', (config) => ({
                customerType: config.customerType,
                canChooseDealer: config.canChooseDealer,
                catalog: config.catalog,
                rows: config.rows,
                posName: config.posName,
                searchQuery: '',
                pendingProduct: null,
                pickerOpen: false,
                addQuantity: 1,
                addLineError: '',
                apply_sale_discount: config.apply_sale_discount,
                sale_discount_amount: config.sale_discount_amount,
                clientName: config.clientName,
                clientPhone: config.clientPhone,
                clients: config.clients,
                clientPanelOpen: false,
                isAdmin: config.isAdmin,
                amountPaid: config.amountPaid,
                paymentType: config.paymentType,
                allowLineDiscount: config.allowLineDiscount,
                submitting: false,

                init() {
                    this.rows.forEach((row) => this.ensureRowPricing(row));
                    this.syncPaymentTypeByCustomer();
                    if (this.customerType === 'dealer' && !this.allowLineDiscount) {
                        this.resetAllRowPricesToCatalog();
                    }
                },

                guardSubmit(event) {
                    if (this.submitting) {
                        event.preventDefault();
                        return;
                    }
                    this.submitting = true;
                },

                ensureRowPricing(row) {
                    const catalog = this.catalogUnitPrice(row);
                    if (row.catalog_unit_price == null) row.catalog_unit_price = catalog;
                    if (row.unit_price == null || row.unit_price === '') row.unit_price = catalog;
                },

                allProductsFlat() {
                    const list = [];
                    for (const dept of this.catalog) {
                        for (const p of (dept.products || [])) {
                            list.push({
                                id: p.id,
                                department_id: dept.id,
                                department_name: dept.name,
                                name: p.name,
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
                        .filter((r) => String(r.product_id) === String(productId))
                        .reduce((s, r) => s + (Number(r.quantity) || 0), 0);
                },

                stockRemainingForProduct(productId) {
                    const p = this.findProduct(productId);
                    if (!p) return 0;
                    return Math.max(0, (Number(p.stock_qty) || 0) - this.qtyInCartForProduct(productId));
                },

                get filteredProducts() {
                    const q = String(this.searchQuery || '').trim().toLowerCase();
                    const all = this.allProductsFlat();
                    if (q.length < 1) return all.slice(0, 10);
                    return all.filter((p) => {
                        const t = (String(p.name || '') + ' ' + String(p.label) + ' ' + String(p.department_name)).toLowerCase();
                        return t.includes(q);
                    }).slice(0, 20);
                },

                findProduct(productId) {
                    if (!productId) return null;
                    for (const dept of this.catalog) {
                        const p = (dept.products || []).find((x) => String(x.id) === String(productId));
                        if (p) return p;
                    }
                    return null;
                },

                rowProductName(row) {
                    if (row.product_name) return row.product_name;
                    const p = this.findProduct(row.product_id);
                    return p ? (p.name || p.label) : '—';
                },

                catalogUnitPrice(row) {
                    if (row.catalog_unit_price != null) return Number(row.catalog_unit_price) || 0;
                    const p = this.findProduct(row.product_id);
                    return p ? Number(p.unit_price) || 0 : 0;
                },

                rowUnitPrice(row) {
                    if (this.customerType === 'dealer' && this.allowLineDiscount) {
                        const v = Number(row.unit_price);
                        return Number.isFinite(v) && v >= 0 ? v : this.catalogUnitPrice(row);
                    }
                    return this.catalogUnitPrice(row);
                },

                syncDealerDiscountMode() {
                    if (this.customerType !== 'dealer') {
                        this.allowLineDiscount = false;
                    }
                },

                resetAllRowPricesToCatalog() {
                    this.rows.forEach((row) => {
                        row.unit_price = this.catalogUnitPrice(row);
                    });
                },

                lineAmount(row) {
                    const unit = this.rowUnitPrice(row);
                    const qty = Number(row.quantity) || 0;
                    return Math.round(unit * qty * 100) / 100;
                },

                priceDiffersFromCatalog(row) {
                    return Math.abs(this.rowUnitPrice(row) - this.catalogUnitPrice(row)) > 0.001;
                },

                resetRowPrice(row) {
                    row.unit_price = this.catalogUnitPrice(row);
                },

                subtotalAmount() {
                    return this.rows.reduce((s, row) => s + this.lineAmount(row), 0);
                },

                saleDiscountNumber() {
                    if (!this.apply_sale_discount) return 0;
                    const v = parseFloat(String(this.sale_discount_amount).replace(',', '.'));
                    return Number.isFinite(v) && v > 0 ? v : 0;
                },

                grandTotal() {
                    return Math.max(0, Math.round((this.subtotalAmount() - this.saleDiscountNumber()) * 100) / 100);
                },

                displayTotalLabel() {
                    if (!this.apply_sale_discount || this.saleDiscountNumber() <= 0) return 'Total à payer';
                    return this.isAdmin ? 'Total à payer' : 'Total (remise en attente)';
                },

                displayTotalAmount() {
                    if (!this.apply_sale_discount || this.saleDiscountNumber() <= 0) return this.subtotalAmount();
                    return this.isAdmin ? this.grandTotal() : this.subtotalAmount();
                },

                availablePaymentTypes() {
                    if (this.customerType === 'dealer') {
                        return [
                            { value: 'cash', label: 'Cash' },
                            { value: 'credit', label: 'Crédit' },
                            { value: 'caution', label: 'Caution' },
                        ];
                    }
                    return [
                        { value: 'cash', label: 'Cash' },
                    ];
                },

                syncPaymentTypeByCustomer() {
                    const allowed = this.availablePaymentTypes().map((x) => x.value);
                    if (!allowed.includes(this.paymentType)) {
                        this.paymentType = this.customerType === 'dealer' ? 'credit' : 'cash';
                    }
                    if (this.paymentType !== 'credit') {
                        this.amountPaid = '0';
                    }
                },

                totalPaidNumber() {
                    const v = parseFloat(String(this.amountPaid).replace(',', '.'));
                    return Number.isFinite(v) && v >= 0 ? v : 0;
                },

                dealerBalance() {
                    if (this.customerType !== 'dealer' || this.paymentType !== 'credit') {
                        return 0;
                    }
                    return Math.max(0, Math.round((this.displayTotalAmount() - this.totalPaidNumber()) * 100) / 100);
                },

                formatUsd(n) {
                    return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                pickProduct(p) {
                    this.pendingProduct = p;
                    this.searchQuery = p.name || p.label;
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
                        this.addLineError = 'Choisissez un produit dans la liste.';
                        return;
                    }
                    const qty = parseInt(String(this.addQuantity), 10);
                    if (!Number.isFinite(qty) || qty < 1) {
                        this.addLineError = 'Quantité invalide (minimum 1).';
                        return;
                    }
                    const avail = this.stockRemainingForProduct(this.pendingProduct.id);
                    if (qty > avail) {
                        this.addLineError = avail <= 0
                            ? 'Stock insuffisant.'
                            : `Stock disponible : ${avail}.`;
                        return;
                    }
                    const catalogPrice = Number(this.pendingProduct.unit_price) || 0;
                    this.rows.push({
                        _key: 'k' + Date.now() + '-' + Math.random().toString(16).slice(2),
                        department_id: String(this.pendingProduct.department_id),
                        product_id: String(this.pendingProduct.id),
                        product_name: this.pendingProduct.name || this.pendingProduct.label,
                        department_name: this.pendingProduct.department_name,
                        quantity: qty,
                        unit_price: catalogPrice,
                        catalog_unit_price: catalogPrice,
                    });
                    this.pendingProduct = null;
                    this.searchQuery = '';
                    this.addQuantity = 1;
                },

                removeRow(index) {
                    this.rows.splice(index, 1);
                },

                get filteredClients() {
                    if (this.customerType !== 'dealer') return [];
                    const term = String(this.clientName || '').trim().toLowerCase();
                    if (!term) return this.clients.slice(0, 8);
                    return this.clients.filter((c) => String(c.name || '').toLowerCase().includes(term)).slice(0, 8);
                },

                get selectedDealerClient() {
                    if (this.customerType !== 'dealer') return null;
                    const name = String(this.clientName || '').trim().toLowerCase();
                    if (!name) return null;
                    return this.clients.find((c) => String(c.name || '').trim().toLowerCase() === name) ?? null;
                },
            }));
        });
    </script>
    @endpush
</x-app-layout>
