<x-app-layout>
    <x-slot name="header">Modifier — {{ $client->name }}</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Modifier le client</h1>
            <p class="mt-1 text-sm text-neutral-600">Nom et téléphone affichés sur la liste des clients.</p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('clients.show', $client) }}" class="text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Fiche client</a>
            <a href="{{ route('clients.index') }}" class="text-neutral-600 hover:text-primary underline-offset-2 hover:underline">Liste des clients</a>
        </div>
    </div>

    <section class="max-w-xl rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
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
                <a href="{{ route('clients.show', $client) }}" class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </section>
</x-app-layout>
