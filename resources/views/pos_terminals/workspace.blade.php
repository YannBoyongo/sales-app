<x-app-layout>
    <x-slot name="header">Caisse — {{ $posTerminal->name }}</x-slot>

    <x-caisse-flow max-width="max-w-5xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Caisse</p>
                        @if ($openShift)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                </span>
                                Session ouverte
                            </span>
                        @else
                            <span class="rounded-full bg-neutral-200/80 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">Session fermée</span>
                        @endif
                    </div>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">{{ $posTerminal->name }}</h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                        Point de vente et déstockage sur l’emplacement lié. Ouvrez une session pour encaisser, consultez les ventes de la session en cours.
                    </p>
                    <p class="mt-3 inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-full border border-neutral-200/80 bg-white/80 px-4 py-1.5 text-sm text-neutral-700 shadow-sm backdrop-blur-sm">
                        <span class="text-neutral-500">Branche</span>
                        <strong class="text-neutral-900">{{ $branch->name }}</strong>
                        <span class="text-neutral-300">·</span>
                        <span class="text-neutral-500">Stock</span>
                        <strong class="text-neutral-900">{{ $posTerminal->location?->name ?? '—' }}</strong>
                    </p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-6">
                @if ($openShift)
                    <div class="rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 via-white to-white p-6 shadow-lg shadow-emerald-900/5 ring-1 ring-emerald-900/5 sm:p-8">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex gap-4">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-md shadow-emerald-500/30">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-emerald-950">Caisse prête</p>
                                    <p class="mt-1 text-sm text-emerald-900/85">
                                        Ouverte le {{ $openShift->opened_at->translatedFormat('d/m/Y à H:i') }}
                                        @if ($openShift->openedByUser)
                                            <span class="text-emerald-800/80">— {{ $openShift->openedByUser->name }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-2 text-xs font-medium text-emerald-800/70">
                                        {{ $shiftSales->count() }} vente{{ $shiftSales->count() > 1 ? 's' : '' }} sur cette session
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                <a
                                    href="{{ route('sales.choose-department', [$branch, $posTerminal]) }}"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Nouvelle vente
                                </a>
                                <a
                                    href="{{ route('pos-terminal.shifts.close-review', [$branch, $posTerminal]) }}"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-neutral-300/90 bg-white px-6 py-3 text-sm font-semibold text-neutral-800 shadow-sm transition hover:bg-neutral-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:ring-offset-2 sm:w-auto"
                                >
                                    <svg class="h-5 w-5 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                    Fermer la session
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm sm:p-8">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-neutral-900">Ventes de cette session</h2>
                                <p class="mt-1 text-sm text-neutral-500">Historique en temps réel pour la session ouverte.</p>
                            </div>
                            <span class="inline-flex w-fit items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold tabular-nums text-neutral-700">
                                {{ $shiftSales->count() }} ligne{{ $shiftSales->count() > 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div class="mt-6 overflow-hidden rounded-xl border border-neutral-100 bg-neutral-50/40">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                                    <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                        <tr>
                                            <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                            <th class="px-4 py-3 whitespace-nowrap">Référence</th>
                                            <th class="px-4 py-3 whitespace-nowrap">Caissier</th>
                                            <th class="px-4 py-3 whitespace-nowrap">Statut paiement</th>
                                            <th class="px-4 py-3 text-right whitespace-nowrap">A payer</th>
                                            <th class="px-4 py-3 text-right whitespace-nowrap">Payé</th>
                                            <th class="px-4 py-3 text-right whitespace-nowrap">Reste</th>
                                            <th class="px-4 py-3 text-right whitespace-nowrap"><span class="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-100 bg-white">
                                        @forelse ($shiftSales as $sale)
                                            <tr @class([
                                                'transition-colors hover:bg-neutral-50/80' => ! $sale->isPendingDiscount(),
                                                'bg-amber-50/90 hover:bg-amber-100/80' => $sale->isPendingDiscount(),
                                            ])>
                                                <td class="px-4 py-3.5 text-neutral-600 whitespace-nowrap">{{ $sale->sold_at->translatedFormat('d/m/Y') }}</td>
                                                <td class="px-4 py-3.5 font-mono text-sm text-neutral-800">{{ $sale->reference }}</td>
                                                <td class="px-4 py-3.5 text-neutral-700">{{ $sale->user?->name ?? '—' }}</td>
                                                <td class="px-4 py-3.5">
                                                    @php($effectiveStatus = $sale->effectivePaymentStatus())
                                                    @if ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_NOT_PAID)
                                                        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-900">Non payé</span>
                                                    @elseif ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_PARTIALLY_PAID)
                                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Partiellement payé</span>
                                                    @else
                                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900">Entièrement payé</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 text-right tabular-nums font-semibold text-neutral-900">{{ \App\Support\Money::usd($sale->expectedPayableAmount()) }}</td>
                                                <td class="px-4 py-3.5 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($sale->paidAmountValue()) }}</td>
                                                <td class="px-4 py-3.5 text-right tabular-nums font-medium text-amber-800">{{ \App\Support\Money::usd($sale->remainingAmountValue()) }}</td>
                                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                                    <a
                                                        href="{{ route('sales.show', [$branch, $sale]) }}"
                                                        class="inline-flex rounded-lg p-2 text-primary transition hover:bg-primary/10"
                                                        title="Voir la vente"
                                                    >
                                                        <span class="sr-only">Voir</span>
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-4 py-14">
                                                    <div class="flex flex-col items-center text-center">
                                                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-400">
                                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </span>
                                                        <p class="mt-4 text-sm font-medium text-neutral-800">Aucune vente pour l’instant</p>
                                                        <p class="mt-1 max-w-sm text-sm text-neutral-500">Démarrez avec <strong class="font-semibold text-neutral-700">Nouvelle vente</strong> ci-dessus.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/90 via-white to-white p-6 shadow-lg shadow-amber-900/5 ring-1 ring-amber-900/5 sm:p-8">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex gap-4">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-900">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-amber-950">Session fermée</p>
                                    <p class="mt-1 max-w-xl text-sm leading-relaxed text-amber-900/90">
                                        Ouvrez une session de caisse pour enregistrer des ventes, suivre les encaissements et rattacher les tickets à ce terminal.
                                    </p>
                                </div>
                            </div>
                            <form action="{{ route('pos-terminal.shifts.open', [$branch, $posTerminal]) }}" method="POST" class="shrink-0">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 lg:w-auto"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                    Ouvrir une session
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 border-t border-neutral-200/80 pt-6">
                    @if ($canPickAnotherBranch)
                        <a
                            href="{{ route('sales.entry') }}"
                            class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-4 py-2.5 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-primary/30 hover:text-primary"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            Autre branche
                        </a>
                    @endif
                    <a
                        href="{{ route('sales.choose-terminal', $branch) }}"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Changer de terminal
                    </a>
                </div>
        </div>
    </x-caisse-flow>
</x-app-layout>
