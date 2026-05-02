<x-app-layout>
    <x-slot name="header">Nouveau bon de commande</x-slot>

    <x-page-header title="Créer un bon de commande" />

    <form action="{{ route('purchase-orders.store') }}" method="POST" class="space-y-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm" x-data="{ rows: [{ product_id: '', quantity_ordered: 1 }] }">
        @csrf

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

        <div class="rounded-md border border-neutral-200 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-neutral-900">Produits</h2>
                <button type="button" class="rounded-md border border-neutral-300 px-3 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-50" @click="rows.push({ product_id: '', quantity_ordered: 1 })">Ajouter une ligne</button>
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
                            <button type="button" class="rounded-md border border-red-200 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50" @click="rows.splice(index, 1)" x-show="rows.length > 1">Retirer</button>
                        </div>
                    </div>
                </template>
            </div>

            <x-input-error :messages="$errors->get('products')" class="mt-2" />
        </div>

        <div class="flex gap-3">
            <x-primary-button>Créer le PO</x-primary-button>
            <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
