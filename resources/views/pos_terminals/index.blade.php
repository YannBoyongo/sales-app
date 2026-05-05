<x-app-layout>
    <x-slot name="header">Terminaux POS — {{ $branch->name }}</x-slot>

    <x-page-header title="Terminaux POS — {{ $branch->name }}" action="Nouveau terminal" :action-href="route('branches.pos-terminals.create', $branch)" />

    @if ($errors->has('terminal'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('terminal') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Nom</th>
                    <th class="px-4 py-3">Emplacement</th>
                    <th class="px-4 py-3">Caissiers</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($terminals as $t)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $t->name }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $t->location?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $t->pos_users_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('branches.pos-terminals.edit', [$branch, $t]) }}" class="text-neutral-700 underline-offset-2 hover:underline">Modifier</a>
                            <form action="{{ route('branches.pos-terminals.destroy', [$branch, $t]) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer ce terminal ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-neutral-600 underline-offset-2 hover:underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-neutral-500">Aucun terminal. Créez-en un et associez un emplacement « Point de vente ».</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        <a href="{{ route('branches.index') }}" class="text-sm text-neutral-600 hover:text-primary">← Branches</a>
    </div>
</x-app-layout>
