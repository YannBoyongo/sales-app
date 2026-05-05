<x-app-layout>
    <x-slot name="header">Modifier l’emplacement — {{ $branch->name }}</x-slot>

    <x-page-header title="Modifier l’emplacement" />

    <p class="mb-4 max-w-lg text-sm text-neutral-600">Branche : <strong>{{ $branch->name }}</strong></p>

    <form action="{{ route('branches.locations.update', [$branch, $location]) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="name" value="Nom de l’emplacement" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $location->name)" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="kind" value="Type" />
            <select id="kind" name="kind" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                @foreach ([\App\Models\Location::KIND_MAIN => \App\Models\Location::kindLabel(\App\Models\Location::KIND_MAIN), \App\Models\Location::KIND_STORAGE => \App\Models\Location::kindLabel(\App\Models\Location::KIND_STORAGE), \App\Models\Location::KIND_POINT_OF_SALE => \App\Models\Location::kindLabel(\App\Models\Location::KIND_POINT_OF_SALE)] as $value => $label)
                    <option value="{{ $value }}" @selected(old('kind', $location->kind) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-neutral-500">Un seul emplacement <strong>principal</strong> par branche. Les ventes se font uniquement depuis les <strong>points de vente</strong>.</p>
            <x-input-error :messages="$errors->get('kind')" class="mt-2" />
        </div>
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('branches.show', $branch) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
