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
            <div class="app-alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="app-alert-warning" role="alert">{{ session('warning') }}</div>
        @endif

        <div class="app-filter-bar mb-4">
            <form method="GET" action="{{ route('pos-terminal.shifts.closed') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Session du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Session au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                </div>
                <div>
                    <label for="registration_filter" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Enregistrement comptable</label>
                    <select id="registration_filter" name="registration" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="unregistered" @selected(($registrationFilter ?? 'unregistered') === 'unregistered')>Non enregistrés</option>
                        <option value="registered" @selected(($registrationFilter ?? '') === 'registered')>Enregistrés</option>
                        <option value="all" @selected(($registrationFilter ?? '') === 'all')>Tous</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                    <button type="submit" class="app-btn-primary">Filtrer</button>
                    <a href="{{ route('pos-terminal.shifts.closed') }}" class="app-btn-secondary">Réinitialiser</a>
                </div>
            </form>
            @if ($errors->has('date_from') || $errors->has('date_to'))
                <p class="mt-3 text-sm text-red-700">{{ $errors->first('date_from') ?: $errors->first('date_to') }}</p>
            @endif
        </div>

        <div class="app-table-shell">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Session du</th>
                        <th class="px-4 py-3">Terminal</th>
                        <th class="px-4 py-3">Ouvert par</th>
                        <th class="px-4 py-3">Fermé par</th>
                        <th class="px-4 py-3">Compta</th>
                        <th class="px-4 py-3 text-right">Ventes</th>
                        <th class="px-4 py-3 text-right">Encaissé (caisse)</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($shifts as $shift)
                        <tr class="hover:bg-neutral-50/80">
                            <td class="px-4 py-3 text-neutral-700">
                                <p class="font-medium text-neutral-900">{{ $shift->effectiveSessionDate()->format('d/m/Y') }}</p>
                                <p class="text-xs text-neutral-500">Fermée le {{ optional($shift->closed_at)->format('d/m/Y H:i') ?? '—' }}</p>
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
                                    <span class="app-badge-success">
                                        Enregistré ({{ (int) $shift->accounting_registered_count }})
                                    </span>
                                @else
                                    <span class="app-badge-warning">
                                        Non enregistré
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $shift->sales_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ number_format((float) ($shift->shift_cash_collected_sum ?? 0), 2, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('pos-terminal.shifts.closed.show', $shift) }}" class="app-btn-secondary !px-3 !py-1.5 text-xs">
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
                                            @if (request()->filled('date_from'))
                                                <input type="hidden" name="date_from" value="{{ request('date_from') }}" />
                                            @endif
                                            @if (request()->filled('date_to'))
                                                <input type="hidden" name="date_to" value="{{ request('date_to') }}" />
                                            @endif
                                            <button
                                                type="submit"
                                                class="app-btn-danger !px-3 !py-1.5 text-xs"
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
                                @if (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null))
                                    Aucun shift fermé pour cette période de session.
                                @else
                                    Aucun shift fermé pour l’instant.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($shifts->hasPages() || $shifts->total() > 0)
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-neutral-600">
                    @if ($shifts->total() > 0)
                        Affichage de {{ $shifts->firstItem() }} à {{ $shifts->lastItem() }} sur {{ $shifts->total() }} session{{ $shifts->total() > 1 ? 's' : '' }}
                    @else
                        0 session
                    @endif
                </p>
                {{ $shifts->links() }}
            </div>
        @endif
    </x-caisse-flow>
</x-app-layout>
