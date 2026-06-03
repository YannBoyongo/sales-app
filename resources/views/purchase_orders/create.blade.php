<x-app-layout>
    <x-slot name="header">Nouveau bon de commande</x-slot>

    <x-caisse-flow
        max-width="max-w-4xl"
        eyebrow="Achats"
        title="Créer un bon de commande"
        description="Choisissez l’emplacement de réception, le fournisseur optionnel et les lignes produits. La réception se fait ensuite sur la fiche du PO."
        :with-card="true"
    >
        <form action="{{ route('purchase-orders.store') }}" method="POST" class="space-y-6" x-data="{ rows: [{ product_id: '', quantity_ordered: 1 }] }">
            @csrf

            <div>
                <x-input-label for="reference" value="Référence" />
                <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full font-mono" :value="old('reference')" required maxlength="100" autocomplete="off" />
                <p class="mt-1 text-xs text-neutral-500">Référence unique du bon de commande (saisie manuelle).</p>
                <x-input-error :messages="$errors->get('reference')" class="mt-2" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-input-label for="location_id" value="Emplacement de réception" />
                    <select id="location_id" name="location_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                        <option value="">— Choisir —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }} ({{ $loc->branch->name }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="supplier" value="Fournisseur (optionnel)" />
                    <x-text-input id="supplier" name="supplier" type="text" class="mt-1 block w-full" :value="old('supplier')" />
                    <x-input-error :messages="$errors->get('supplier')" class="mt-2" />
                </div>
            </div>

            <div class="app-panel app-panel-body bg-slate-50/50">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-900">Produits</h2>
                    <button type="button" class="app-btn-secondary !px-3 !py-1 text-xs" @click="rows.push({ product_id: '', quantity_ordered: 1 })">Ajouter une ligne</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="grid gap-3 md:grid-cols-[1fr_140px_100px]">
                            <div>
                                <select :name="`products[${index}][product_id]`" x-model="row.product_id" class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                                    <option value="">Produit</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->department->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <input :name="`products[${index}][quantity_ordered]`" x-model="row.quantity_ordered" type="number" min="1" class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required />
                            </div>
                            <div class="flex items-center justify-end">
                                <button type="button" class="app-btn-danger !px-3 !py-1 text-xs" @click="rows.splice(index, 1)" x-show="rows.length > 1">Retirer</button>
                            </div>
                        </div>
                    </template>
                </div>

                <x-input-error :messages="$errors->get('products')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 border-t border-neutral-100 pt-6">
                <x-primary-button>Créer le Bon de commande</x-primary-button>
                <a href="{{ route('purchase-orders.index') }}" class="app-btn-secondary">Annuler</a>
            </div>
        </form>
    </x-caisse-flow>
</x-app-layout>
