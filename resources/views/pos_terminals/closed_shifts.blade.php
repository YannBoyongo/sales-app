<x-app-layout>
    <x-slot name="header">Shifts fermés</x-slot>

    <x-caisse-flow
        max-width="max-w-7xl"
        :with-card="false"
        eyebrow="Caisse"
        title="Historique des shifts fermés"
        :description="$closedShiftsDescription"
    >
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950" role="status">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="alert">{{ session('warning') }}</div>
        @endif

        <div class="mb-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('pos-terminal.shifts.closed') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="registration_filter" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Enregistrement comptable</label>
                    <select id="registration_filter" name="registration" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="unregistered" @selected(($registrationFilter ?? 'unregistered') === 'unregistered')>Non enregistrés</option>
                        <option value="registered" @selected(($registrationFilter ?? '') === 'registered')>Enregistrés</option>
                        <option value="all" @selected(($registrationFilter ?? '') === 'all')>Tous</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">Filtrer</button>
                    <a href="{{ route('pos-terminal.shifts.closed') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="px-4 py-3">Fermé le</th>
                        <th class="px-4 py-3">Terminal</th>
                        <th class="px-4 py-3">Ouvert par</th>
                        <th class="px-4 py-3">Fermé par</th>
                        <th class="px-4 py-3">Compta</th>
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
                            <td class="px-4 py-3">
                                @if ((int) ($shift->accounting_registered_count ?? 0) > 0)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                        Enregistré ({{ (int) $shift->accounting_registered_count }})
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                        Non enregistré
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $shift->sales_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ number_format((float) ($shift->sales_sum_total_amount ?? 0), 2, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('pos-terminal.shifts.closed.show', $shift) }}" class="inline-flex items-center rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                        Voir
                                    </a>
                                    @if (auth()->user()?->isAdmin() && (int) $shift->sales_count === 0 && (int) ($shift->accounting_registered_count ?? 0) === 0)
                                        <form
                                            action="{{ route('pos-terminal.shifts.closed.destroy', $shift) }}"
                                            method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Supprimer cette session fermée sans vente ? Cette action est définitive.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            @if (request()->filled('registration'))
                                                <input type="hidden" name="registration" value="{{ request('registration') }}" />
                                            @endif
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-800 hover:bg-red-100"
                                            >
                                                Supprimer
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">
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
