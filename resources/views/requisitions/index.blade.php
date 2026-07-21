<x-app-layout>
    <x-slot name="header">Réquisitions</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Achats</p>
                    <h1 class="app-page-title">Réquisitions</h1>
                    <p class="app-page-desc max-w-2xl">
                        Demandez des produits en stock pour votre emplacement et suivez l’état de chaque réquisition.
                    </p>
                </div>
                <a
                    href="{{ route('requisitions.create') }}"
                    class="app-btn-primary shrink-0"
                >
                    Nouvelle réquisition
                </a>
            </div>
        </x-slot>

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3">Référence</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Créé par</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($requisitions as $requisition)
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $requisition->reference }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $requisition->date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $requisition->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($requisition->status === \App\Models\Requisition::STATUS_APPROVED)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->status === \App\Models\Requisition::STATUS_REJECTED)
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->status === \App\Models\Requisition::STATUS_FULFILLED)
                                    <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">{{ $requisition->statusLabel() }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-800">{{ $requisition->statusLabel() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('requisitions.show', $requisition) }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">Voir</a>
                                    @if ($requisition->status === \App\Models\Requisition::STATUS_OPEN)
                                        <a href="{{ route('requisitions.edit', $requisition) }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">Modifier</a>
                                        <form
                                            action="{{ route('requisitions.destroy', $requisition) }}"
                                            method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Supprimer définitivement cette réquisition ?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800 shadow-sm hover:bg-red-100">Supprimer</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Aucune réquisition.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisitions->links() }}</div>
    </x-caisse-flow>
</x-app-layout>
