<x-app-layout>
    <x-slot name="header">Nouveau client</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Nouveau client</h1>
            <p class="mt-1 text-sm text-neutral-600">Créer un client pour les ventes à crédit.</p>
        </div>
        <a href="{{ route('clients.index') }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Retour aux clients</a>
    </div>

    <section class="max-w-xl rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('clients.store') }}" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="name" value="Nom du client" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('clients.index') }}" class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
                <x-primary-button>Créer le client</x-primary-button>
            </div>
        </form>
    </section>
</x-app-layout>
