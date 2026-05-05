<x-app-layout>
    <x-slot name="header">Seuil d’alerte</x-slot>

    <x-caisse-flow
        max-width="max-w-2xl"
        :with-card="false"
        eyebrow="Stock"
        title="Seuil d’alerte"
        :description="'Produit « ' . e($stock->product->name) . ' » — emplacement ' . e($stock->location->name) . ' (' . e($stock->location->branch->name) . '). Quantité actuelle : ' . e((string) $stock->quantity) . ' (modifiée via mouvements ou ventes).'"
    >
        <form action="{{ route('stocks.update', $stock) }}" method="POST" class="space-y-4 rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm sm:p-8">
            @csrf
            @method('PATCH')
            @if (! empty($stocksIndexQuery['branch'] ?? null))
                <input type="hidden" name="branch" value="{{ $stocksIndexQuery['branch'] }}" />
            @endif
            <div>
                <x-input-label for="minimum_stock" value="Seuil minimum (alerte si quantité inférieure)" />
                <x-text-input id="minimum_stock" name="minimum_stock" type="number" min="0" class="mt-1 block w-full" :value="old('minimum_stock', $stock->minimum_stock)" placeholder="Laisser vide pour désactiver" />
                <p class="mt-1 text-xs text-neutral-500">Champ optionnel. Les entrées/sorties se font dans « Mouvements de stock ».</p>
                <x-input-error :messages="$errors->get('minimum_stock')" class="mt-2" />
            </div>
            <div class="flex gap-3 pt-2">
                <x-primary-button>Enregistrer</x-primary-button>
                <a href="{{ route('stocks.index', array_filter($stocksIndexQuery ?? [])) }}" class="inline-flex items-center rounded-xl border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50">Retour</a>
            </div>
        </form>
    </x-caisse-flow>
</x-app-layout>
