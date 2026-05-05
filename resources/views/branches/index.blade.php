<x-app-layout>
    <x-slot name="header">Branches</x-slot>

    <x-page-header title="Branches" action="Nouvelle branche" :action-href="route('branches.create')" />

    @if ($errors->has('branch'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('branch') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Nom</th>
                    <th class="px-4 py-3">Emplacements</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($branches as $branch)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $branch->name }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $branch->locations_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-0.5">
                                <a
                                    href="{{ route('branches.show', $branch) }}"
                                    class="inline-flex rounded-md p-1.5 text-primary hover:bg-primary/10"
                                    title="Voir la fiche"
                                >
                                    <span class="sr-only">Voir</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                <a
                                    href="{{ route('branches.edit', $branch) }}"
                                    class="inline-flex rounded-md p-1.5 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Modifier"
                                >
                                    <span class="sr-only">Modifier</span>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="inline-flex" onsubmit="return confirm('Supprimer cette branche ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="inline-flex rounded-md p-1.5 text-red-600 hover:bg-red-50 hover:text-red-800"
                                        title="Supprimer"
                                    >
                                        <span class="sr-only">Supprimer</span>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $branches->links() }}</div>
</x-app-layout>
