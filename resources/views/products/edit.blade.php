<x-app-layout>
    <x-slot name="header">Modifier le produit</x-slot>

    <x-page-header title="Modifier le produit" />

    <form action="{{ route('products.update', $product) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="department_id" value="Département" />
            <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected(old('department_id', $product->department_id) == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="name" value="Nom" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product->name)" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="sku" value="Référence (SKU)" />
            <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full" :value="old('sku', $product->sku)" />
            <x-input-error :messages="$errors->get('sku')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="description" value="Description" />
            <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary">{{ old('description', $product->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="unit_price" value="Prix unitaire (USD)" />
            <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('unit_price', $product->unit_price)" required />
            <x-input-error :messages="$errors->get('unit_price')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="minimum_stock" value="Seuil stock min. (global)" />
            <x-text-input id="minimum_stock" name="minimum_stock" type="number" min="0" class="mt-1 block w-full" :value="old('minimum_stock', $product->minimum_stock)" placeholder="Optionnel" />
            <p class="mt-1 text-xs text-neutral-500">S’applique si le seuil de l’emplacement est vide.</p>
            <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
        </div>
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
