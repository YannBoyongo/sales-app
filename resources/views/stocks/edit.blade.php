<x-app-layout>
    <x-slot name="header">Seuil d’alerte</x-slot>

    <x-page-header title="Seuil d’alerte — {{ $stock->product->name }}" />

    <div class="mb-4 text-sm text-neutral-600">
        <p><span class="font-medium text-neutral-800">Emplacement :</span> {{ $stock->location->name }} ({{ $stock->location->branch->name }})</p>
        <p class="mt-1"><span class="font-medium text-neutral-800">Quantité actuelle :</span> {{ $stock->quantity }} (modifiée uniquement via mouvements ou ventes)</p>
    </div>

    <form action="{{ route('stocks.update', $stock) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="minimum_stock" value="Seuil minimum (alerte si quantité inférieure)" />
            <x-text-input id="minimum_stock" name="minimum_stock" type="number" min="0" class="mt-1 block w-full" :value="old('minimum_stock', $stock->minimum_stock)" placeholder="Laisser vide pour désactiver" />
            <p class="mt-1 text-xs text-neutral-500">Champ optionnel. Les entrées/sorties se font dans « Mouvements de stock ».</p>
            <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
        </div>
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('stocks.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Retour</a>
        </div>
    </form>
</x-app-layout>
