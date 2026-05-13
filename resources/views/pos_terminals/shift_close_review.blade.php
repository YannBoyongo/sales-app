<x-app-layout>
    <x-slot name="header">Fermeture de session — {{ $posTerminal->name }}</x-slot>

    <x-caisse-flow
        max-width="max-w-3xl"
        :with-card="false"
        title="Fermer la session"
        description="Vérifiez les totaux par département, puis confirmez pour clôturer la caisse sur ce terminal."
        :context-line="'<span class=\'text-neutral-500\'>Terminal</span> <strong class=\'text-neutral-900\'>' . e($posTerminal->name) . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Ouverte le</span> <strong class=\'text-neutral-900\'>' . e($shift->opened_at->translatedFormat('d/m/Y H:i')) . '</strong>'"
    >
        <div class="space-y-8">
            @if ($pendingDiscountCount > 0)
                <div class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-amber-100/20 p-5 shadow-sm">
                    <div class="flex gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-200/70 text-amber-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold text-amber-950">Remises en attente</p>
                            <p class="mt-1 text-sm text-amber-900/90">
                                {{ $pendingDiscountCount }} vente{{ $pendingDiscountCount > 1 ? 's' : '' }} avec remise non encore approuvée.
                                Ces ventes sont <strong>exclues</strong> des totaux ci-dessous et la session ne peut pas être fermée tant que la validation n’est pas faite.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @error('shift_close')
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm sm:p-8">
                <h2 class="text-lg font-semibold text-neutral-900">Totaux par département</h2>
                <p class="mt-1 text-sm text-neutral-500">Chaque vente est rattachée au département des articles saisis. Le <strong>total</strong> est l’argent réellement encaissé (acomptes dealer inclus), pas le montant facture si une partie reste au crédit.</p>

                <div class="mt-6 overflow-hidden rounded-xl border border-neutral-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                <tr>
                                    <th class="px-4 py-3">Département</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Ventes</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Encaissé</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 bg-white">
                                @forelse ($summaries as $row)
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
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-10 text-center text-sm text-neutral-500">
                                            Aucune vente sur cette session. Vous pouvez fermer la session sans encaissement.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (count($summaries) > 0)
                                <tfoot>
                                    <tr class="border-t-2 border-neutral-200 bg-neutral-50/80">
                                        <th scope="row" class="px-4 py-4 text-left text-sm font-semibold text-neutral-900">Total général</th>
                                        <td class="px-4 py-4 text-right text-sm font-semibold tabular-nums text-neutral-700">
                                            {{ $closableSalesCount }}
                                        </td>
                                        <td class="px-4 py-4 text-right text-lg font-bold tabular-nums text-primary">{{ \App\Support\Money::usd($grandTotal) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                <form action="{{ route('pos-terminal.shifts.close', [$branch, $posTerminal]) }}" method="POST" class="mt-8 space-y-6 border-t border-neutral-100 pt-8">
                    @csrf
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 transition hover:border-primary/25 hover:bg-primary/[0.03]">
                        <input
                            type="checkbox"
                            name="confirm_totals"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-primary focus:ring-primary"
                            required
                        />
                        <span class="text-sm leading-relaxed text-neutral-700">
                            <strong class="text-neutral-900">Je confirme</strong> avoir vérifié les encaissements par département et le total général encaissé (<span class="tabular-nums font-semibold">{{ \App\Support\Money::usd($grandTotal) }}</span>) avant de fermer la session.
                        </span>
                    </label>
                    @error('confirm_totals')
                        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Confirmer et fermer la session
                        </button>
                        <a
                            href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}"
                            class="inline-flex items-center justify-center rounded-xl border border-neutral-300 bg-white px-6 py-3 text-sm font-semibold text-neutral-800 shadow-sm transition hover:bg-neutral-50"
                        >
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </x-caisse-flow>
</x-app-layout>
