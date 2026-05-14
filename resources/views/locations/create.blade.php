<x-app-layout>
    <x-slot name="header">Nouvel emplacement — {{ $branch->name }}</x-slot>

    <x-page-header title="Nouvel emplacement" />

    <p class="mb-4 max-w-lg text-sm text-neutral-600">Branche : <strong>{{ $branch->name }}</strong></p>

    <form action="{{ route('branches.locations.store', $branch) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <x-input-label for="name" value="Nom de l’emplacement" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="kind" value="Type" />
            <select id="kind" name="kind" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary focus:ring-primary" required>
                @foreach ([\App\Models\Location::KIND_MAIN => \App\Models\Location::kindLabel(\App\Models\Location::KIND_MAIN), \App\Models\Location::KIND_STORAGE => \App\Models\Location::kindLabel(\App\Models\Location::KIND_STORAGE), \App\Models\Location::KIND_POINT_OF_SALE => \App\Models\Location::kindLabel(\App\Models\Location::KIND_POINT_OF_SALE)] as $value => $label)
                    <option value="{{ $value }}" @selected(old('kind', \App\Models\Location::KIND_STORAGE) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-neutral-500">Un seul <strong>principal</strong> par branche (entrepôt central). Les <strong>points de vente</strong> servent aux encaissements ; approvisionnez-les depuis le principal par transfert.</p>
            <x-input-error :messages="$errors->get('kind')" class="mt-2" />
        </div>
        <fieldset class="rounded-md border border-neutral-200 p-4">
            <legend class="text-sm font-medium text-neutral-800">Magasiniers ayant accès à cet emplacement</legend>
            <p class="mb-3 text-xs text-neutral-500">Cochez les comptes magasiniers qui gèrent le stock à cet emplacement (plusieurs choix possibles).</p>
            @if ($stockManagerCandidates->isEmpty())
                <p class="text-sm text-neutral-600">Aucun utilisateur avec le rôle « Magasinier » pour le moment.</p>
            @else
                <ul class="max-h-48 space-y-2 overflow-y-auto">
                    @foreach ($stockManagerCandidates as $candidate)
                        @php
                            $selected = collect(old('stock_manager_ids', []))->map(fn ($id) => (int) $id)->contains((int) $candidate->id);
                        @endphp
                        <li class="flex items-center gap-2">
                            <input
                                id="stock_manager_{{ $candidate->id }}"
                                type="checkbox"
                                name="stock_manager_ids[]"
                                value="{{ $candidate->id }}"
                                @checked($selected)
                                class="rounded border-neutral-300 text-primary shadow-sm focus:border-primary focus:ring-primary"
                            />
                            <label for="stock_manager_{{ $candidate->id }}" class="text-sm text-neutral-800">{{ $candidate->name }}</label>
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-input-error :messages="$errors->get('stock_manager_ids')" class="mt-2" />
            <x-input-error :messages="$errors->get('stock_manager_ids.*')" class="mt-2" />
        </fieldset>
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('branches.show', $branch) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
