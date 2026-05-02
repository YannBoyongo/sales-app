<x-app-layout>
    <x-slot name="header">Nouveau transfert de stock</x-slot>

    <x-page-header title="Nouveau transfert de stock" />

    @if ($errors->has('stock'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('stock') }}</div>
    @endif

    <form
        action="{{ route('stock-transfers.store') }}"
        method="POST"
        class="max-w-4xl space-y-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm"
        x-data="{ rows: @js(old('items', [['product_id' => '', 'quantity' => 1]])) }"
    >
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="from_location_id" value="Emplacement source" />
                <select id="from_location_id" name="from_location_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                    <option value="">— Choisir —</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(old('from_location_id') == $loc->id)>{{ $loc->name }} — {{ $loc->branch->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('from_location_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="to_location_id" value="Emplacement destination" />
                <select id="to_location_id" name="to_location_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                    <option value="">— Choisir —</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(old('to_location_id') == $loc->id)>{{ $loc->name }} — {{ $loc->branch->name }}</option>
                    @endforeach
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
            <button type="submit" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">Confirmer le transfert</button>
            <a href="{{ route('stock-transfers.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
