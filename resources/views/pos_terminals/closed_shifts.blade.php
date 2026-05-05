<x-app-layout>
    <x-slot name="header">Shifts fermés</x-slot>

    <x-caisse-flow
        max-width="max-w-7xl"
        :with-card="false"
        eyebrow="Caisse"
        title="Historique des shifts fermés"
        description="Liste des sessions de caisse déjà clôturées pour les terminaux auxquels vous avez accès."
    >
        <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="px-4 py-3">Fermé le</th>
                        <th class="px-4 py-3">Terminal</th>
                        <th class="px-4 py-3">Ouvert par</th>
                        <th class="px-4 py-3">Fermé par</th>
                        <th class="px-4 py-3 text-right">Ventes</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($shifts as $shift)
                        <tr class="hover:bg-neutral-50/80">
                            <td class="px-4 py-3 text-neutral-700">
                                <p class="font-medium text-neutral-900">{{ optional($shift->closed_at)->format('d/m/Y H:i') ?? '—' }}</p>
                                <p class="text-xs text-neutral-500">Ouvert: {{ optional($shift->opened_at)->format('d/m/Y H:i') ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-neutral-900">{{ $shift->posTerminal?->name ?? '—' }}</p>
                                <p class="text-xs text-neutral-500">
                                    {{ $shift->posTerminal?->branch?->name ?? '—' }}
                                    @if ($shift->posTerminal?->location?->name)
                                        · {{ $shift->posTerminal->location->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $shift->openedByUser?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $shift->closedByUser?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $shift->sales_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ number_format((float) ($shift->sales_sum_total_amount ?? 0), 2, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('pos-terminal.shifts.closed.show', $shift) }}" class="inline-flex items-center rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                    Voir
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">
                                Aucun shift fermé pour l’instant.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $shifts->links() }}
        </div>
    </x-caisse-flow>
</x-app-layout>
