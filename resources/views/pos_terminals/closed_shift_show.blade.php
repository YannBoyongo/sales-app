<x-app-layout>
    <x-slot name="header">Détail shift fermé — {{ $shift->posTerminal?->name }}</x-slot>

    <x-caisse-flow
        max-width="max-w-3xl"
        :with-card="false"
        title="Détail de session fermée"
        description="Consultation des ventes par département pour cette session clôturée."
        :context-line="'<span class=\'text-neutral-500\'>Terminal</span> <strong class=\'text-neutral-900\'>' . e($shift->posTerminal?->name ?? '—') . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Session du</span> <strong class=\'text-neutral-900\'>' . e($shift->effectiveSessionDate()->translatedFormat('d/m/Y')) . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Fermée le</span> <strong class=\'text-neutral-900\'>' . e($shift->effectiveClosedAt()->translatedFormat('d/m/Y H:i')) . '</strong>'"
    >
        <div
            class="space-y-8"
            x-data="{
                open: false,
                departmentId: '',
                deptLabel: '',
                voucherReference: '',
                prepare(deptId, label, defaultRef) {
                    this.departmentId = deptId === null || deptId === undefined ? '' : String(deptId);
                    this.deptLabel = label;
                    this.voucherReference = defaultRef;
                    this.open = true;
                    this.$nextTick(() => {
                        const el = this.$refs.voucherReferenceInput;
                        if (el) { el.focus(); el.select?.(); }
                    });
                },
            }"
            @keydown.escape.window="open = false"
        >
            <div class="app-panel app-panel-body sm:p-8">
                <h2 class="text-lg font-semibold text-neutral-900">Totaux par département</h2>
                <p class="mt-1 text-sm text-neutral-500">Les montants par département correspondent aux <strong>encaissements réels</strong> (acomptes inclus). Les ventes dealer à solde figurent au crédit client pour la partie non payée.</p>
                <p class="mt-1 text-xs text-neutral-500">Session ouverte par {{ $shift->openedByUser?->name ?? '—' }} et fermée par {{ $shift->closedByUser?->name ?? '—' }}.</p>

                <div class="app-table-shell mt-6">
                        <table class="min-w-full text-sm">
                            <thead>
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
                                        $defaultVoucherNo = sprintf('CV-SHIFT-%d-%s', $shift->id, $row['department']?->id ?? 'ND');
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
                                            @if ($row['bon_already_created'])
                                                <span class="app-badge-success">
                                                    Déjà saisi
                                                </span>
                                            @elseif (auth()->user()?->canPushClosedShiftCashEntry())
                                                <button
                                                    type="button"
                                                    class="app-btn-secondary !px-3 !py-2 text-xs"
                                                    @click="prepare(@js($row['department']?->id), @js($row['label']), @js($defaultVoucherNo))"
                                                >
                                                    Créer le bon de caisse
                                                </button>
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

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ route('pos-terminal.shifts.closed') }}" class="app-btn-secondary !px-6 !py-3">
                        Retour à la liste
                    </a>
                    @if ($canReopen ?? false)
                        <form
                            action="{{ route('pos-terminal.shifts.closed.reopen', $shift) }}"
                            method="POST"
                            onsubmit="return confirm('Rouvrir cette session ? Les ventes ajoutées seront datées du {{ $shift->effectiveSessionDate()->translatedFormat('d/m/Y') }} et la date de clôture d\'origine sera conservée.');"
                        >
                            @csrf
                            <button type="submit" class="app-btn-primary !px-6 !py-3">
                                Rouvrir la session
                            </button>
                        </form>
                    @elseif (auth()->user()?->isAdmin() && ! ($canReopen ?? true))
                        <p class="text-sm text-neutral-500">Session déjà enregistrée en comptabilité — réouverture impossible.</p>
                    @endif
                </div>
            </div>

            @if (auth()->user()?->canPushClosedShiftCashEntry())
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
                    <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="shift-bon-modal-title"
                        class="app-panel app-panel-body relative z-10 w-full max-w-lg"
                        @click.stop
                    >
                        <h2 id="shift-bon-modal-title" class="text-lg font-semibold text-neutral-900">Créer le bon de caisse</h2>
                        <p class="mt-1 text-sm text-neutral-500"><span class="font-medium text-neutral-700">Département</span> : <span x-text="deptLabel"></span></p>

                        <form
                            action="{{ route('pos-terminal.shifts.closed.push-accounting', $shift) }}"
                            method="POST"
                            class="mt-5 space-y-4"
                        >
                            @csrf
                            <input type="hidden" name="department_id" x-bind:value="departmentId">
                            <div>
                                <label for="shift-voucher-reference" class="block text-xs font-semibold uppercase tracking-wide text-neutral-600">Référence du bon</label>
                                <input
                                    id="shift-voucher-reference"
                                    type="text"
                                    name="voucher_no"
                                    x-model="voucherReference"
                                    x-ref="voucherReferenceInput"
                                    required
                                    maxlength="100"
                                    class="mt-2 block w-full rounded-xl border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                    placeholder="{{ sprintf('CV-SHIFT-%d-…', $shift->id) }}"
                                    autocomplete="off"
                                >
                                @error('voucher_no')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs text-neutral-500">Indiquez le numéro de bon désiré. Vous pouvez garder la suggestion ou la modifier.</p>
                            </div>
                            <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                                <button type="button" class="app-btn-secondary" @click="open = false">
                                    Annuler
                                </button>
                                <button type="submit" class="app-btn-primary">
                                    Confirmer la création
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </x-caisse-flow>
</x-app-layout>
