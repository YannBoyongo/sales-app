<x-app-layout>
    <x-slot name="header">Terminal — {{ $posTerminal->name }}</x-slot>

    <x-page-header title="Modifier le terminal — {{ $branch->name }}" />

    <form action="{{ route('branches.pos-terminals.update', [$branch, $posTerminal]) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="name" value="Nom du terminal" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $posTerminal->name)" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div class="rounded-md border border-neutral-100 bg-neutral-50 px-3 py-2 text-sm text-neutral-600">
            Emplacement : <strong>{{ $posTerminal->location?->name ?? '—' }}</strong> (non modifiable)
        </div>
        @if ($eligibleUsers->isNotEmpty())
            <div>
                <p class="text-sm font-medium text-neutral-800">Caissiers affectés</p>
                <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-md border border-neutral-200 p-3">
                    @foreach ($eligibleUsers as $u)
                        <li class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="pu-{{ $u->id }}"
                                name="pos_user_ids[]"
                                value="{{ $u->id }}"
                                class="rounded border-neutral-300 text-primary focus:ring-primary"
                                @checked(in_array((string) $u->id, old('pos_user_ids', $posTerminal->posUsers->pluck('id')->map(fn ($id) => (string) $id)->all()), true))
                            />
                            <label for="pu-{{ $u->id }}" class="text-sm text-neutral-700">{{ $u->name }}</label>
                        </li>
                    @endforeach
                </ul>
                <x-input-error :messages="$errors->get('pos_user_ids')" class="mt-2" />
            </div>
        @else
            <p class="text-sm text-amber-800">Aucun utilisateur avec le rôle « Caissier (terminal) » sur cette branche. Créez-en un dans la gestion des utilisateurs.</p>
        @endif
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('branches.pos-terminals.index', $branch) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
