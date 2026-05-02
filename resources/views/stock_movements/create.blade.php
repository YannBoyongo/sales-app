<x-app-layout>
    <x-slot name="header">Nouveau mouvement</x-slot>

    <x-page-header title="Nouveau mouvement de stock" />

    @if ($errors->has('stock'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('stock') }}</div>
    @endif

    <form action="{{ route('stock-movements.store') }}" method="POST" class="max-w-xl space-y-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm" x-data="{ type: '{{ old('type', 'entry') }}' }">
        @csrf

        <div>
            <x-input-label for="type" value="Type de mouvement" />
            <select id="type" name="type" x-model="type" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                <option value="entry">Entrée</option>
                <option value="exit">Sortie</option>
                <option value="transfer">Transfert entre emplacements</option>
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="product_id" value="Produit" />
            <select id="product_id" name="product_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                <option value="">— Choisir —</option>
                @foreach ($products as $p)
                    <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} ({{ $p->department->name }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="quantity" value="Quantité" />
            <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity')" required />
            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
        </div>

        <div x-show="type === 'exit' || type === 'transfer'" x-cloak>
            <x-input-label for="from_location_id" value="Emplacement source" />
            <select id="from_location_id" name="from_location_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" :required="type === 'exit' || type === 'transfer'">
                <option value="">— Choisir —</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected(old('from_location_id') == $loc->id)>{{ $loc->name }} ({{ $loc->branch->name }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('from_location_id')" class="mt-2" />
        </div>

        <div x-show="type === 'entry' || type === 'transfer'" x-cloak>
            <x-input-label for="to_location_id" value="Emplacement destination" />
            <select id="to_location_id" name="to_location_id" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" :required="type === 'entry' || type === 'transfer'">
                <option value="">— Choisir —</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected(old('to_location_id') == $loc->id)>{{ $loc->name }} ({{ $loc->branch->name }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('to_location_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="notes" value="Notes (optionnel)" />
            <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3">
            <x-primary-button>Enregistrer le mouvement</x-primary-button>
            <a href="{{ route('stock-movements.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
