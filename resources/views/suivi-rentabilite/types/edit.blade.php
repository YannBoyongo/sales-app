<x-app-layout>
    <x-slot name="header">Modifier le type de transaction</x-slot>

    <x-caisse-flow max-width="max-w-lg" :with-card="false">
        <x-slot name="header">
            <div>
                <p class="app-page-eyebrow">Suivi de rentabilité</p>
                <h1 class="app-page-title">Modifier le type de transaction</h1>
            </div>
        </x-slot>

        <form action="{{ route('suivi-rentabilite.types.update', $costTransactionType) }}" method="POST" class="space-y-4 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Nom</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $costTransactionType->name) }}"
                    required
                    maxlength="255"
                    autofocus
                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-wrap gap-2 border-t border-neutral-100 pt-4">
                <button type="submit" class="app-btn-primary">Enregistrer</button>
                <a href="{{ route('suivi-rentabilite.types.index') }}" class="app-btn-secondary">Annuler</a>
            </div>
        </form>
    </x-caisse-flow>
</x-app-layout>
