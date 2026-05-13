<x-app-layout>
    <x-slot name="header">Détail shift fermé — {{ $shift->posTerminal?->name }}</x-slot>

    <x-caisse-flow
        max-width="max-w-3xl"
        :with-card="false"
        title="Détail de session fermée"
        description="Consultation des ventes par département pour cette session clôturée."
        :context-line="'<span class=\'text-neutral-500\'>Terminal</span> <strong class=\'text-neutral-900\'>' . e($shift->posTerminal?->name ?? '—') . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Fermée le</span> <strong class=\'text-neutral-900\'>' . e(optional($shift->closed_at)->translatedFormat('d/m/Y H:i') ?? '—') . '</strong>'"
    >
        <div class="space-y-8">
            <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm sm:p-8">
                <h2 class="text-lg font-semibold text-neutral-900">Totaux par département</h2>
                <p class="mt-1 text-sm text-neutral-500">Les montants par département correspondent aux <strong>encaissements réels</strong> (acomptes inclus). Les ventes dealer à solde figurent au crédit client pour la partie non payée.</p>
                <p class="mt-1 text-xs text-neutral-500">Session ouverte par {{ $shift->openedByUser?->name ?? '—' }} et fermée par {{ $shift->closedByUser?->name ?? '—' }}.</p>

                <div class="mt-6 overflow-hidden rounded-xl border border-neutral-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                <tr>
                                    <th class="px-4 py-3">Département</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Ventes</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Encaissé</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Entrée en caisse</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 bg-white">
                                @forelse ($summaries as $row)
                                    @php
                                        $deptVoucherNo = sprintf('CV-SHIFT-%d-%s', $shift->id, $row['department']?->id ?? 'ND');
                                        $isPushed = in_array($deptVoucherNo, $pushedShiftDepartmentVoucherNos, true);
                                    @endphp
                                    <tr class="align-top hover:bg-neutral-50/60">
                                        <td class="px-4 py-4">
                                            <span class="font-medium text-neutral-900">{{ $row['label'] }}</span>
                                            @if ($row['sales']->isNotEmpty())
                                                <ul class="mt-2 space-y-1 text-xs text-neutral-500">
                                                    @foreach ($row['sales'] as $sale)
                                                        @php $cash = $sale->cashForShiftTotals(); @endphp
                                                        <li>
                                                            <a href="{{ route('sales.show', [$branch, $sale]) }}" class="font-mono text-primary hover:underline">{{ $sale->reference }}</a>
                                                            <span class="text-neutral-400">·</span>
                                                            {{ \App\Support\Money::usd($cash) }}
                                                            @if (bccomp((string) $sale->total_amount, $cash, 2) !== 0)
                                                                <span class="text-neutral-400">(fact. {{ \App\Support\Money::usd($sale->total_amount) }})</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right tabular-nums text-neutral-700">{{ $row['sales_count'] }}</td>
                                        <td class="px-4 py-4 text-right text-base font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($row['total']) }}</td>
                                        <td class="px-4 py-4 text-right">
                                            @if ($isPushed)
                                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                                                    Déjà saisi
                                                </span>
                                            @elseif (auth()->user()?->canPushClosedShiftCashEntry())
                                                <form
                                                    action="{{ route('pos-terminal.shifts.closed.push-accounting', $shift) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Créer le bon de caisse (entrée) pour ce département ? Vous le comptabiliserez ensuite depuis Bons de caisse.');"
                                                    class="inline"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="department_id" value="{{ $row['department']?->id }}">
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 rounded-xl border border-primary/25 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                                    >
                                                        Créer le bon de caisse
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-neutral-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-neutral-500">
                                            Aucune vente rattachée à cette session.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (count($summaries) > 0)
                                <tfoot>
                                    <tr class="border-t-2 border-neutral-200 bg-neutral-50/80">
                                        <th scope="row" class="px-4 py-4 text-left text-sm font-semibold text-neutral-900">Total général</th>
                                        <td class="px-4 py-4 text-right text-sm font-semibold tabular-nums text-neutral-700">
                                            {{ $shift->sales->count() }}
                                        </td>
                                        <td class="px-4 py-4 text-right text-lg font-bold tabular-nums text-primary">{{ \App\Support\Money::usd($grandTotal) }}</td>
                                        <td class="px-4 py-4"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="{{ route('pos-terminal.shifts.closed') }}" class="inline-flex items-center rounded-xl border border-neutral-300 bg-white px-6 py-3 text-sm font-semibold text-neutral-800 shadow-sm transition hover:bg-neutral-50">
                        Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </x-caisse-flow>
</x-app-layout>
