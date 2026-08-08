<x-app-layout>
    <x-slot name="header">Rapport de shift — {{ $shift?->posTerminal?->name ?? ($terminal->name ?? 'Point de vente') }}</x-slot>

    <x-caisse-flow
        max-width="max-w-5xl"
        :with-card="false"
        eyebrow="Point de vente"
        title="Rapport de shift"
        description="Totaux vendus par branche et emplacement de vente pour la session en cours."
        :context-line="$shift ? '<span class=\'text-neutral-500\'>Point de vente</span> <strong class=\'text-neutral-900\'>' . e($shift->posTerminal?->name ?? '-') . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Session du</span> <strong class=\'text-neutral-900\'>' . e($shift->effectiveSessionDate()->translatedFormat('d/m/Y')) . '</strong>' . ($shift->closed_at ? '<span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Fermée le</span> <strong class=\'text-neutral-900\'>' . e($shift->effectiveClosedAt()->translatedFormat('d/m/Y H:i')) . '</strong>' : '<span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-emerald-700 font-medium\'>Session ouverte</span>') : null"
    >
        <div class="app-panel app-panel-body sm:p-8">
            @if (session('success'))
                <div class="app-alert-success mb-4" role="status">{{ session('success') }}</div>
            @endif
            @if (session('warning'))
                <div class="app-alert-warning mb-4" role="alert">{{ session('warning') }}</div>
            @endif
            @if ($errors->has('shift'))
                <div class="app-alert-danger mb-4" role="alert">{{ $errors->first('shift') }}</div>
            @endif

            @if ($shift)
                @if (($directReport ?? false) && ($recentShifts ?? collect())->count() > 1)
                    <form method="GET" action="{{ route('point-de-vente.shifts.report') }}" class="mb-6 max-w-md">
                        <label for="shift" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Session</label>
                        <select
                            id="shift"
                            name="shift"
                            class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            onchange="this.form.submit()"
                        >
                            @foreach ($recentShifts as $recentShift)
                                <option value="{{ $recentShift->id }}" @selected((int) $shift->id === (int) $recentShift->id)>
                                    {{ $recentShift->effectiveSessionDate()->format('d/m/Y') }}
                                    @if ($recentShift->closed_at)
                                        — Fermée
                                    @else
                                        — Ouverte
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <p class="text-sm text-neutral-500">
                    Ouverte par <strong class="text-neutral-800">{{ $shift->openedByUser?->name ?? '-' }}</strong>
                    @if ($shift->closed_at)
                        · Fermée par <strong class="text-neutral-800">{{ $shift->closedByUser?->name ?? '-' }}</strong>
                    @endif
                </p>
            @else
                <div class="app-alert-warning">
                    <p class="font-semibold text-amber-950">Aucune session</p>
                    <p class="mt-1 text-sm text-amber-900/90">Ouvrez une session sur le point de vente pour commencer à enregistrer des ventes.</p>
                </div>
            @endif

            <div class="app-table-shell mt-6">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Branche</th>
                            <th class="px-4 py-3">Emplacement (vendu à)</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Ventes</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Total vendu</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Encaissé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white">
                        @forelse ($summaries as $row)
                            <tr class="align-top hover:bg-neutral-50/60">
                                <td class="px-4 py-4 font-medium text-neutral-900">{{ $row['branch']?->name ?? '—' }}</td>
                                <td class="px-4 py-4">
                                    <span class="font-medium text-neutral-900">{{ $row['location']?->name ?? '—' }}</span>
                                    @if ($row['sales']->isNotEmpty())
                                        <ul class="mt-2 space-y-1 text-xs text-neutral-500">
                                            @foreach ($row['sales'] as $sale)
                                                <li>
                                                    <span class="font-mono">{{ $sale->reference }}</span>
                                                    <span class="text-neutral-400">·</span>
                                                    {{ \App\Support\Money::usd($sale->total_amount) }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right tabular-nums text-neutral-700">{{ $row['sales_count'] }}</td>
                                <td class="px-4 py-4 text-right text-base font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($row['total_sold']) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-neutral-700">{{ \App\Support\Money::usd($row['total_collected']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-neutral-500">
                                    @if ($shift)
                                        Aucune vente enregistrée pour cette session.
                                    @else
                                        Aucune vente à afficher.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($summaries) > 0)
                        <tfoot>
                            <tr class="border-t-2 border-neutral-200 bg-neutral-50/80">
                                <th scope="row" colspan="2" class="px-4 py-4 text-left text-sm font-semibold text-neutral-900">Total général</th>
                                <td class="px-4 py-4 text-right text-sm font-semibold tabular-nums text-neutral-700">{{ $grandSalesCount }}</td>
                                <td class="px-4 py-4 text-right text-lg font-bold tabular-nums text-primary">{{ \App\Support\Money::usd($grandTotalSold) }}</td>
                                <td class="px-4 py-4 text-right text-base font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($grandTotalCollected) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($shift && ($terminal ?? $shift->posTerminal))
                @php
                    $reportTerminal = $terminal ?? $shift->posTerminal;
                @endphp
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ route('point-de-vente.workspace', [$reportTerminal->branch, $reportTerminal]) }}" class="app-btn-secondary !px-6 !py-3">
                        Retour au point de vente
                    </a>
                    @if ($canDeleteShifts ?? false)
                        <form
                            action="{{ route('point-de-vente.shifts.report.destroy', $shift) }}"
                            method="POST"
                            class="inline"
                            onsubmit="return confirm(@js(($grandSalesCount ?? 0) > 0
                                ? 'Supprimer cette session et ses '.$grandSalesCount.' vente(s) ? Le stock sera réintégré.'
                                : 'Supprimer cette session ?'));"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="app-btn-danger !px-6 !py-3">
                                Supprimer la session
                            </button>
                        </form>
                    @endif
                </div>
            @elseif (! ($directReport ?? false))
                <div class="mt-6">
                    <a href="{{ route('point-de-vente.shifts.report') }}" class="app-btn-secondary !px-6 !py-3">
                        Retour à la liste
                    </a>
                </div>
            @endif
        </div>
    </x-caisse-flow>
</x-app-layout>
