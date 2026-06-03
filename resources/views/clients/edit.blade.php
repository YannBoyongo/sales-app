<x-app-layout>
    <x-slot name="header">Modifier — {{ $client->name }}</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="app-page-title">Modifier le client</h1>
            <p class="app-page-desc">Nom et téléphone affichés sur la liste des clients.</p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('clients.show', $client) }}" class="text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Fiche client</a>
            <a href="{{ route('clients.index') }}" class="text-neutral-600 hover:text-primary underline-offset-2 hover:underline">Liste des clients</a>
        </div>
    </div>

    <section class="app-panel app-panel-body max-w-xl">
        <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <x-input-label for="name" value="Nom du client" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $client->name)" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Téléphone (optionnel)" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $client->phone)" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('clients.show', $client) }}" class="app-btn-secondary">Annuler</a>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </section>
</x-app-layout>
