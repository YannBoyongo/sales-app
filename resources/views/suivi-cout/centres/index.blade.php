<x-app-layout>
    <x-slot name="header">Centres de coût</x-slot>

    <x-caisse-flow max-width="max-w-4xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Suivi de coût</p>
                    <h1 class="app-page-title">Centres de coût</h1>
                    <p class="app-page-desc max-w-2xl">
                        Gérez les centres de coût utilisés dans les écritures financières.
                    </p>
                </div>
                <a href="{{ route('suivi-cout') }}" class="app-btn-secondary shrink-0 self-start">
                    Retour au suivi
                </a>
            </div>
        </x-slot>

        @if ($errors->has('cost_center'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('cost_center') }}
            </div>
        @endif

        <form action="{{ route('suivi-cout.centres.store') }}" method="POST" class="mb-6 flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Nouveau centre de coût</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    required
                    maxlength="255"
                    placeholder="Nom du centre"
                    class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="app-btn-primary shrink-0">Ajouter</button>
        </form>

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Écritures</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($costCenters as $center)
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $center->name }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $center->entries_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('suivi-cout.centres.edit', $center) }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                        Modifier
                                    </a>
                                    <form
                                        action="{{ route('suivi-cout.centres.destroy', $center) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Supprimer ce centre de coût ?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800 shadow-sm hover:bg-red-100">
                                            Supprimer
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-12 text-center text-neutral-500">Aucun centre de coût.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-caisse-flow>
</x-app-layout>
