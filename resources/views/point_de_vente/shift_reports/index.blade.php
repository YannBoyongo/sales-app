<x-app-layout>
    <x-slot name="header">Rapport de shift — Point de vente</x-slot>

    <x-caisse-flow
        max-width="max-w-7xl"
        :with-card="false"
        eyebrow="Point de vente"
        title="Rapport de shift"
        description="Sessions du point de vente mobile avec totaux vendus par branche et emplacement de vente."
    >
        @if (session('success'))
            <div class="app-alert-success mb-4" role="status">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="app-alert-warning mb-4" role="alert">{{ session('warning') }}</div>
        @endif
        @if ($errors->has('shift'))
            <div class="app-alert-danger mb-4" role="alert">{{ $errors->first('shift') }}</div>
        @endif

        <div class="app-filter-bar mb-4">
            <form method="GET" action="{{ route('point-de-vente.shifts.report') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Session du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Session au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                </div>
                <div>
                    <label for="pos_terminal_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Point de vente</label>
                    <select id="pos_terminal_id" name="pos_terminal_id" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Tous</option>
                        @foreach ($terminals as $terminal)
                            <option value="{{ $terminal->id }}" @selected((string) ($filters['pos_terminal_id'] ?? '') === (string) $terminal->id)>
                                {{ $terminal->name }} ({{ $terminal->branch?->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                    <button type="submit" class="app-btn-primary">Filtrer</button>
                    <a href="{{ route('point-de-vente.shifts.report') }}" class="app-btn-secondary">Réinitialiser</a>
                </div>
            </form>
            @if ($errors->any())
                <p class="mt-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif
        </div>

        <div class="app-table-shell">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Session</th>
                        <th class="px-4 py-3">Point de vente</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3">Ouvert par</th>
                        <th class="px-4 py-3 text-right">Ventes</th>
                        <th class="px-4 py-3 text-right">Total vendu</th>
                        <th class="px-4 py-3 text-right">Encaissé</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($shifts as $shift)
                        <tr class="hover:bg-neutral-50/80">
                            <td class="px-4 py-3 text-neutral-700">
                                <p class="font-medium text-neutral-900">{{ $shift->effectiveSessionDate()->format('d/m/Y') }}</p>
                                @if ($shift->closed_at)
                                    <p class="text-xs text-neutral-500">Fermée le {{ $shift->effectiveClosedAt()->format('d/m/Y H:i') }}</p>
                                @else
                                    <p class="text-xs text-emerald-700">En cours</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-neutral-900">{{ $shift->posTerminal?->name ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($shift->closed_at)
                                    <span class="app-badge-neutral">Fermée</span>
                                @else
                                    <span class="app-badge-success">Ouverte</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $shift->openedByUser?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $shift->sales_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($shift->total_sold ?? 0) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ \App\Support\Money::usd($shift->total_collected ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('point-de-vente.shifts.report.show', $shift) }}" class="app-btn-secondary !px-3 !py-1.5 text-xs">
                                        Détail
                                    </a>
                                    @if ($canDeleteShifts ?? false)
                                        <form
                                            action="{{ route('point-de-vente.shifts.report.destroy', $shift) }}"
                                            method="POST"
                                            class="inline"
                                            onsubmit="return confirm(@js($shift->sales_count > 0
                                                ? 'Supprimer cette session et ses '.$shift->sales_count.' vente(s) ? Le stock sera réintégré.'
                                                : 'Supprimer cette session ?'));"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                            <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                            <input type="hidden" name="pos_terminal_id" value="{{ $filters['pos_terminal_id'] ?? '' }}">
                                            <button type="submit" class="app-btn-danger !px-3 !py-1.5 text-xs">
                                                Supprimer
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-neutral-500">
                                Aucune session point de vente trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($shifts->hasPages())
            <div class="mt-4">
                {{ $shifts->links() }}
            </div>
        @endif
    </x-caisse-flow>
</x-app-layout>
