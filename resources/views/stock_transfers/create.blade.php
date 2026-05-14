<x-app-layout>
    <x-slot name="header">Nouveau transfert de stock</x-slot>

    <x-page-header title="Nouveau transfert de stock" />

    @if ($errors->has('stock'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('stock') }}</div>
    @endif

    @php
        $scopeOld = old('transfer_scope', \App\Models\StockTransfer::SCOPE_INTERNAL);
        if (! $canExternal && $scopeOld === \App\Models\StockTransfer::SCOPE_EXTERNAL) {
            $scopeOld = \App\Models\StockTransfer::SCOPE_INTERNAL;
        }
    @endphp

    <form
        action="{{ route('stock-transfers.store') }}"
        method="POST"
        class="max-w-4xl space-y-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm"
        x-data="{
            transferScope: @js($scopeOld),
            isAdminPicker: @js($picksBranchForTransfer),
            branches: @js($branchesForTransfer),
            internalBranchId: @js(old('context_internal_branch', '')),
            externalFromBranchId: @js(old('context_external_from_branch', '')),
            externalToBranchId: @js(old('context_external_to_branch', '')),
            internalFromLocations: @js($internalFromLocations),
            internalToLocations: @js($internalToLocations),
            externalPickMode: @js($externalPickMode),
            externalLocations: @js($externalLocations),
            externalFromLocations: @js($externalFromLocations),
            externalToLocations: @js($externalToLocations),
            canExternal: @js($canExternal),
            fromId: @js(old('from_location_id', '')),
            toId: @js(old('to_location_id', '')),
            rows: @js(old('items', [['product_id' => '', 'quantity' => 1]])),
            resetTransferType() {
                this.fromId = '';
                this.toId = '';
                if (!this.isAdminPicker) return;
                this.internalBranchId = '';
                this.externalFromBranchId = '';
                this.externalToBranchId = '';
            },
            onInternalBranchChange() {
                this.fromId = '';
                this.toId = '';
            },
            onExternalFromBranchChange() {
                this.fromId = '';
                this.toId = '';
                if (String(this.externalToBranchId) === String(this.externalFromBranchId)) {
                    this.externalToBranchId = '';
                }
            },
            onExternalToBranchChange() {
                this.fromId = '';
                this.toId = '';
            },
            externalToBranchOptions() {
                if (!this.isAdminPicker || this.transferScope !== 'external') return [];
                if (!this.externalFromBranchId) return [];
                return this.branches.filter(b => String(b.id) !== String(this.externalFromBranchId));
            },
            branchPickerIncomplete() {
                if (!this.isAdminPicker) return false;
                if (this.transferScope === 'internal') return !this.internalBranchId;
                if (this.transferScope === 'external') {
                    return !this.externalFromBranchId || !this.externalToBranchId
                        || String(this.externalFromBranchId) === String(this.externalToBranchId);
                }
                return false;
            },
            get fromOptions() {
                if (this.transferScope === 'internal') {
                    if (!this.isAdminPicker) return this.internalFromLocations;
                    const bid = Number(this.internalBranchId);
                    if (!bid) return [];
                    return this.internalFromLocations.filter(l => Number(l.branch_id) === bid);
                }
                if (!this.isAdminPicker) {
                    return this.externalPickMode === 'single_list' ? this.externalLocations : this.externalFromLocations;
                }
                const bid = Number(this.externalFromBranchId);
                if (!bid) return [];
                return this.externalLocations.filter(l => Number(l.branch_id) === bid);
            },
            filteredToOptions() {
                if (this.transferScope === 'internal') {
                    if (!this.isAdminPicker) {
                        if (!this.fromId) return [];
                        const from = this.internalFromLocations.find(x => String(x.id) === String(this.fromId));
                        if (!from) return [];
                        return this.internalToLocations.filter(t => Number(t.branch_id) === Number(from.branch_id) && String(t.id) !== String(this.fromId));
                    }
                    const bid = Number(this.internalBranchId);
                    if (!bid || !this.fromId) return [];
                    return this.internalToLocations.filter(t => Number(t.branch_id) === bid && String(t.id) !== String(this.fromId));
                }
                if (!this.fromId) return [];
                const from = this.fromOptions.find(x => String(x.id) === String(this.fromId));
                if (!from) return [];
                if (!this.isAdminPicker) {
                    if (this.externalPickMode === 'single_list') {
                        return this.externalLocations.filter(t => Number(t.branch_id) !== Number(from.branch_id) && String(t.id) !== String(this.fromId));
                    }
                    return this.externalToLocations.filter(t => String(t.id) !== String(this.fromId));
                }
                const toBid = Number(this.externalToBranchId);
                if (!toBid) return [];
                return this.externalLocations.filter(t => Number(t.branch_id) === toBid && Number(t.branch_id) !== Number(from.branch_id) && String(t.id) !== String(this.fromId));
            },
        }"
    >
        @csrf

        @if ($picksBranchForTransfer)
            <input type="hidden" name="context_internal_branch" x-bind:value="transferScope === 'internal' ? internalBranchId : ''" />
            <input type="hidden" name="context_external_from_branch" x-bind:value="transferScope === 'external' ? externalFromBranchId : ''" />
            <input type="hidden" name="context_external_to_branch" x-bind:value="transferScope === 'external' ? externalToBranchId : ''" />
        @else
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">
                <span class="font-medium text-neutral-600">Branche :</span>
                <span class="ml-1 font-semibold text-neutral-900">{{ $userBranchName ?? '—' }}</span>
                <span class="mt-1 block text-xs text-neutral-500">Les emplacements proposés correspondent automatiquement à votre branche.</span>
            </div>
        @endif

        <fieldset class="space-y-3 rounded-lg border border-neutral-200 bg-neutral-50/80 p-4">
            <legend class="px-1 text-sm font-semibold text-neutral-900">Type de transfert</legend>
            <div class="flex flex-col gap-3 sm:flex-row sm:gap-8">
                <label class="flex cursor-pointer items-start gap-2 text-sm text-neutral-800">
                    <input
                        type="radio"
                        name="transfer_scope"
                        value="internal"
                        x-model="transferScope"
                        @change="resetTransferType()"
                        class="mt-1 border-neutral-300 text-primary focus:ring-primary"
                    />
                    <span>
                        <span class="font-medium">Interne</span>
                        <span class="mt-0.5 block text-xs text-neutral-600">De l’entrepôt principal ou secondaire vers un <strong>point de vente</strong> de la <strong>même</strong> branche.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 text-sm text-neutral-800" :class="!canExternal ? 'opacity-50 cursor-not-allowed' : ''">
                    <input
                        type="radio"
                        name="transfer_scope"
                        value="external"
                        x-model="transferScope"
                        @change="resetTransferType()"
                        :disabled="!canExternal"
                        class="mt-1 border-neutral-300 text-primary focus:ring-primary"
                    />
                    <span>
                        <span class="font-medium">Externe</span>
                        <span class="mt-0.5 block text-xs text-neutral-600">D’une <strong>branche</strong> à une <strong>autre</strong> (entrepôt principal ou secondaire uniquement, pas les points de vente).</span>
                    </span>
                </label>
            </div>
            @if (! $canExternal)
                <p class="text-xs text-amber-900">Le transfert externe n’est pas disponible : il faut au moins deux branches avec des entrepôts, ou des emplacements d’une autre branche accessibles selon votre profil.</p>
            @endif
            <x-input-error :messages="$errors->get('transfer_scope')" class="mt-2" />
        </fieldset>

        @if ($picksBranchForTransfer)
            <div x-show="transferScope === 'internal'" class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <x-input-label for="internal_branch_id" value="Branche (transfert interne)" />
                <select
                    id="internal_branch_id"
                    x-model="internalBranchId"
                    @change="onInternalBranchChange()"
                    class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                >
                    <option value="">— Choisir la branche —</option>
                    <template x-for="b in branches" :key="'ib-' + b.id">
                        <option :value="String(b.id)" x-text="b.name"></option>
                    </template>
                </select>
                <p class="mt-2 text-xs text-neutral-500">Les listes d’emplacements source et point de vente sont filtrées sur cette branche.</p>
            </div>

            <div x-show="transferScope === 'external'" x-cloak class="grid gap-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:grid-cols-2">
                <div>
                    <x-input-label for="external_from_branch_id" value="Branche source" />
                    <select
                        id="external_from_branch_id"
                        x-model="externalFromBranchId"
                        @change="onExternalFromBranchChange()"
                        class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                    >
                        <option value="">— Choisir —</option>
                        <template x-for="b in branches" :key="'efb-' + b.id">
                            <option :value="String(b.id)" x-text="b.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <x-input-label for="external_to_branch_id" value="Branche destination" />
                    <select
                        id="external_to_branch_id"
                        x-model="externalToBranchId"
                        @change="onExternalToBranchChange()"
                        class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                    >
                        <option value="">— Choisir —</option>
                        <template x-for="b in externalToBranchOptions()" :key="'etb-' + b.id">
                            <option :value="String(b.id)" x-text="b.name"></option>
                        </template>
                    </select>
                </div>
                <p class="text-xs text-neutral-500 sm:col-span-2">Les entrepôts affichés correspondent aux branches choisies.</p>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="from_location_id">
                    <span x-show="transferScope === 'internal'">Source (principal ou secondaire)</span>
                    <span x-show="transferScope === 'external'" x-cloak>Source (entrepôt — autre branche possible)</span>
                </x-input-label>
                <select
                    id="from_location_id"
                    name="from_location_id"
                    x-model="fromId"
                    class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                    required
                >
                    <option value="">— Choisir —</option>
                    <template x-for="loc in fromOptions" :key="'f-' + transferScope + '-' + loc.id">
                        <option :value="String(loc.id)" x-text="loc.name + ' — ' + loc.branch_name"></option>
                    </template>
                </select>
                <x-input-error :messages="$errors->get('from_location_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="to_location_id">
                    <span x-show="transferScope === 'internal'">Destination (point de vente)</span>
                    <span x-show="transferScope === 'external'" x-cloak>Destination (entrepôt — autre branche)</span>
                </x-input-label>
                <select
                    id="to_location_id"
                    name="to_location_id"
                    x-model="toId"
                    class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                    required
                >
                    <option value="">— Choisir —</option>
                    <template x-for="loc in filteredToOptions()" :key="'t-' + transferScope + '-' + loc.id">
                        <option :value="String(loc.id)" x-text="loc.name + ' — ' + loc.branch_name"></option>
                    </template>
                </select>
                <x-input-error :messages="$errors->get('to_location_id')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="transferred_at" value="Date du transfert" />
            <input id="transferred_at" name="transferred_at" type="date" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" value="{{ old('transferred_at', now()->toDateString()) }}" required />
            <x-input-error :messages="$errors->get('transferred_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="notes" value="Notes (optionnel)" />
            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" placeholder="Référence interne, commentaire…">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="rounded-lg border border-neutral-200 bg-neutral-50/50 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-neutral-900">Articles et quantités</h2>
                <button type="button" class="rounded-md border border-neutral-300 bg-white px-3 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-50" @click="rows.push({ product_id: '', quantity: 1 })">Ajouter une ligne</button>
            </div>
            <p class="mb-3 text-xs text-neutral-500">Les quantités sont retirées du stock source et ajoutées à la destination. Les lignes avec le même produit seront regroupées.</p>
            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1">
                            <label class="block text-xs font-medium text-neutral-600" :for="'product_' + index">Produit</label>
                            <select :id="'product_' + index" :name="`items[${index}][product_id]`" x-model="row.product_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" required>
                                <option value="">— Choisir —</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}@if($p->sku) — {{ $p->sku }}@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-28">
                            <label class="block text-xs font-medium text-neutral-600" :for="'qty_' + index">Qté</label>
                            <input :id="'qty_' + index" :name="`items[${index}][quantity]`" type="number" min="1" x-model="row.quantity" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" required />
                        </div>
                        <button type="button" class="rounded-md border border-red-200 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50" @click="rows.splice(index, 1)" x-show="rows.length > 1">Retirer</button>
                    </div>
                </template>
            </div>
            <x-input-error :messages="$errors->get('items')" class="mt-2" />
            <x-input-error :messages="$errors->get('items.*.product_id')" class="mt-2" />
            <x-input-error :messages="$errors->get('items.*.quantity')" class="mt-2" />
        </div>

        <div class="flex flex-wrap gap-3">
            <button
                type="submit"
                class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                x-bind:disabled="branchPickerIncomplete()"
            >Confirmer le transfert</button>
            <a href="{{ route('stock-transfers.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
