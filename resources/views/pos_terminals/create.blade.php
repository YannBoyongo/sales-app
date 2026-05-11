<x-app-layout>
    <x-slot name="header">Nouveau terminal — {{ $branch->name }}</x-slot>

    <x-page-header title="Nouveau terminal POS — {{ $branch->name }}" />

    <form action="{{ route('branches.pos-terminals.store', $branch) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <x-input-label for="name" value="Nom du terminal" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="location_id" value="Emplacement" />
            <select id="location_id" name="location_id" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                <option value="">— Choisir —</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected((string) old('location_id') === (string) $loc->id)>{{ $loc->name }} ({{ \App\Models\Location::kindLabel($loc->kind) }})</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-neutral-500">Tout emplacement de la branche peut être choisi. Chaque emplacement ne peut être lié qu’à un seul terminal.</p>
            <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
        </div>
        @if ($eligibleUsers->isNotEmpty())
            <div>
                <p class="text-sm font-medium text-neutral-800">Caissiers (rôle « Caissier (terminal) » sur cette branche)</p>
                <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-md border border-neutral-200 p-3">
                    @foreach ($eligibleUsers as $u)
                        <li class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="pu-{{ $u->id }}"
                                name="pos_user_ids[]"
                                value="{{ $u->id }}"
                                class="rounded border-neutral-300 text-primary focus:ring-primary"
                                @checked(in_array((string) $u->id, old('pos_user_ids', []), true))
                            />
                            <label for="pu-{{ $u->id }}" class="text-sm text-neutral-700">{{ $u->name }}</label>
                        </li>
                    @endforeach
                </ul>
                <x-input-error :messages="$errors->get('pos_user_ids')" class="mt-2" />
            </div>
        @endif
        <div class="flex gap-3">
            <x-primary-button>Créer</x-primary-button>
            <a href="{{ route('branches.show', $branch) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
