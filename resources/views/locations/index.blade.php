<x-app-layout>
    <x-slot name="header">Emplacements</x-slot>

    <x-page-header title="Emplacements" action="Nouvel emplacement" :action-href="route('locations.create')" />

    @if ($errors->has('location'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('location') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Nom</th>
                    <th class="px-4 py-3">Branche</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($locations as $location)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $location->name }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $location->branch->name }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('locations.edit', $location) }}" class="text-neutral-700 underline-offset-2 hover:underline">Modifier</a>
                            <form action="{{ route('locations.destroy', $location) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer cet emplacement ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-neutral-600 underline-offset-2 hover:underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $locations->links() }}</div>
</x-app-layout>
