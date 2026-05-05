<x-app-layout>
    <x-slot name="header">Modifier la vente {{ $sale->reference }}</x-slot>

    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Administration</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900">Modifier la vente {{ $sale->reference }}</h1>
            <p class="mt-2 text-sm text-neutral-600">{{ $branch->name }} — lignes de produit inchangées</p>
        </div>
        <a href="{{ route('sales.show', [$branch, $sale]) }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Retour à la vente</a>
    </div>

    <form action="{{ route('sales.update', [$branch, $sale]) }}" method="POST" class="max-w-lg space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')

        <div>
            <x-input-label for="sold_at" value="Date et heure de vente" />
            <input
                id="sold_at"
                name="sold_at"
                type="datetime-local"
                value="{{ old('sold_at', $sale->sold_at->format('Y-m-d\TH:i')) }}"
                class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-primary focus:ring-primary"
                required
            />
            <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="payment_type" value="Mode de paiement" />
            <select id="payment_type" name="payment_type" class="mt-1 block w-full rounded-lg border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                <option value="cash" @selected(old('payment_type', $sale->payment_type) === 'cash')>Cash</option>
                <option value="credit" @selected(old('payment_type', $sale->payment_type) === 'credit')>Crédit</option>
            </select>
            <x-input-error :messages="$errors->get('payment_type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="client_name" value="Nom du client" />
            <x-text-input
                id="client_name"
                name="client_name"
                type="text"
                class="mt-1 block w-full"
                :value="old('client_name', $sale->payment_type === 'credit' ? $sale->client?->name : $sale->client_name)"
            />
            <p class="mt-1 text-xs text-neutral-500">Crédit : obligatoire (fiche client). Cash : optionnel, enregistré sur la vente uniquement.</p>
            <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="client_phone" value="Téléphone (optionnel)" />
            <x-text-input
                id="client_phone"
                name="client_phone"
                type="text"
                class="mt-1 block w-full"
                :value="old('client_phone', $sale->payment_type === 'credit' ? $sale->client?->phone : $sale->client_phone)"
            />
            <x-input-error :messages="$errors->get('client_phone')" class="mt-2" />
        </div>

        <div class="flex flex-wrap gap-3 border-t border-neutral-100 pt-4">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('sales.show', [$branch, $sale]) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
