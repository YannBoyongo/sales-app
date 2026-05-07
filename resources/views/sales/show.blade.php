<x-app-layout>
    <x-slot name="header">Vente {{ $sale->reference }}</x-slot>
    @php
        $effectiveStatus = $sale->effectivePaymentStatus();
        $expectedAmount = $sale->expectedPayableAmount();
        $paidAmount = $sale->paidAmountValue();
        $remainingAmount = $sale->remainingAmountValue();
        $backToSalesListUrl = $sale->posShift && $sale->posShift->posTerminal
            ? route('pos-terminal.workspace', [$branch, $sale->posShift->posTerminal])
            : route('sales.overview');
    @endphp

    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Facturation</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">Vente {{ $sale->reference }}</h1>
            <p class="mt-2 text-sm text-neutral-600">
                {{ $branch->name }} · {{ $sale->sold_at->translatedFormat('d/m/Y à H:i') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sales.print-large', [$branch, $sale]) }}" target="_blank" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Imprimer facture A4</a>
            <a href="{{ route('sales.print-small', [$branch, $sale]) }}" target="_blank" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Imprimer ticket POS</a>
            @if (auth()->user()?->isAdmin())
                <form
                    action="{{ route('sales.destroy', [$branch, $sale]) }}"
                    method="POST"
                    class="inline-flex"
                    onsubmit="return confirm('Supprimer définitivement cette vente ? Le stock sera réintégré sur les emplacements concernés.');"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                        Supprimer la vente
                    </button>
                </form>
            @endif
            <a href="{{ $backToSalesListUrl }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Liste des ventes</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif

    @if ($errors->has('sale'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('sale') }}</div>
    @endif
    @if ($errors->has('sale_payment'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('sale_payment') }}</div>
    @endif

    @if ($sale->isPendingDiscount())
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Remise en attente d’approbation</p>
            <p class="mt-1">
                Montant demandé : <span class="tabular-nums font-medium">{{ \App\Support\Money::usd($sale->discount_requested_amount ?? 0) }}</span>
                @if ($sale->discountRequestedByUser)
                    — demandé par {{ $sale->discountRequestedByUser->name }}
                    @if ($sale->discount_requested_at)
                        le {{ $sale->discount_requested_at->translatedFormat('d/m/Y à H:i') }}
                    @endif
                @endif
            </p>
            <p class="mt-2 text-xs text-amber-900/90">Le total enregistré reste le sous-total jusqu’à décision d’un administrateur.</p>
            @if (auth()->user()->canApproveSaleDiscounts())
                <div class="mt-4 flex flex-wrap gap-2">
                    <form action="{{ route('sales.approve-discount', [$branch, $sale]) }}" method="POST" onsubmit="return confirm('Approuver cette remise ?');">
                        @csrf
                        <x-primary-button type="submit">Approuver la remise</x-primary-button>
                    </form>
                    <form action="{{ route('sales.reject-discount', [$branch, $sale]) }}" method="POST" onsubmit="return confirm('Refuser la remise ? La vente sera confirmée sans remise.');">
                        @csrf
                        <x-secondary-button type="submit">Refuser la remise</x-secondary-button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-neutral-900">Produits vendus</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="py-3 pr-4">Produit</th>
                            <th class="py-3 pr-4">Emplacement</th>
                            <th class="py-3 pr-4 text-right">Qté</th>
                            <th class="py-3 pr-4 text-right">PU</th>
                            <th class="py-3 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($sale->items as $item)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-neutral-900">{{ $item->product->name }}</td>
                                <td class="py-3 pr-4 text-neutral-600">{{ $item->location->name }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ \App\Support\Money::usd($item->unit_price) }}</td>
                                <td class="py-3 text-right tabular-nums">{{ \App\Support\Money::usd($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-neutral-200 bg-neutral-50/60">
                        <tr>
                            <th colspan="4" scope="row" class="py-3 pr-4 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                Montant à payer
                            </th>
                            <td class="py-3 text-right tabular-nums font-semibold text-neutral-900">
                                {{ \App\Support\Money::usd($sale->subtotal_amount ?? $sale->total_amount) }}
                            </td>
                        </tr>
                        @if (($sale->isPendingDiscount() && (float) ($sale->discount_requested_amount ?? 0) > 0) || ((float) ($sale->discount_amount ?? 0) > 0))
                            <tr>
                                <th colspan="4" scope="row" class="py-2 pr-4 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                    Remise
                                </th>
                                <td class="py-2 text-right tabular-nums font-medium text-amber-800">
                                    − {{ \App\Support\Money::usd($sale->isPendingDiscount() ? ($sale->discount_requested_amount ?? 0) : ($sale->discount_amount ?? 0)) }}
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="4" scope="row" class="py-3 pr-4 text-left text-sm font-semibold text-neutral-800">
                                Nouveau total
                            </th>
                            <td class="py-3 text-right tabular-nums text-base font-bold text-primary">
                                {{ \App\Support\Money::usd($expectedAmount) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <aside class="h-fit rounded-2xl border border-neutral-200 bg-gradient-to-b from-white to-neutral-50 p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Résumé</h2>
            <div class="mt-4 space-y-3">
                @if ($sale->posShift && $sale->posShift->posTerminal)
                    <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                        <p class="text-xs text-neutral-500">Session POS</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $sale->posShift->posTerminal->name }}</p>
                        <p class="mt-1 text-xs text-neutral-500">
                            Ouverte {{ $sale->posShift->opened_at->translatedFormat('d/m/Y H:i') }}
                            @if ($sale->posShift->closed_at)
                                · fermée {{ $sale->posShift->closed_at->translatedFormat('d/m/Y H:i') }}
                            @endif
                        </p>
                    </div>
                @endif
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Client</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $sale->displayClientName() ?? '—' }}</p>
                    @if ($sale->displayClientPhone())
                        <p class="mt-1 text-sm text-neutral-600">{{ $sale->displayClientPhone() }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 space-y-2 text-sm">
                    <p class="text-xs text-neutral-500">Statut paiement</p>
                    <p>
                        @if ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_NOT_PAID)
                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">Non payé</span>
                        @elseif ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_PARTIALLY_PAID)
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Partiellement payé</span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Entièrement payé</span>
                        @endif
                    </p>
                    <div class="border-t border-neutral-100 pt-2">
                        <p class="flex justify-between gap-2"><span class="text-neutral-600">Montant à payer</span><span class="tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($expectedAmount) }}</span></p>
                        <p class="mt-1 flex justify-between gap-2"><span class="text-neutral-600">Montant payé</span><span class="tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($paidAmount) }}</span></p>
                        <p class="mt-1 flex justify-between gap-2"><span class="text-neutral-600">Reste à payer</span><span class="tabular-nums font-semibold {{ (float) $remainingAmount > 0 ? 'text-amber-800' : 'text-emerald-700' }}">{{ \App\Support\Money::usd($remainingAmount) }}</span></p>
                    </div>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 space-y-3 text-sm">
                    <p class="text-xs text-neutral-500">Confirmation de règlement</p>
                    @if ($sale->isPendingDiscount())
                        <p class="text-xs text-amber-800">Paiement indisponible pendant qu’une remise est en attente d’approbation.</p>
                    @elseif ((float) $remainingAmount <= 0)
                        <p class="text-xs text-emerald-700">Cette vente est déjà soldée.</p>
                    @else
                        <form action="{{ route('sales.confirm-paid', [$branch, $sale]) }}" method="POST" onsubmit="return confirm('Confirmer cette vente comme entièrement payée ?');">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white transition hover:opacity-95"
                            >
                                Confirmer cette vente comme payée
                            </button>
                        </form>
                    @endif
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 space-y-2 text-sm">
                    <p class="text-xs text-neutral-500">Détail facture</p>
                    <div class="flex justify-between gap-2">
                        <span class="text-neutral-600">Sous-total</span>
                        <span class="tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($sale->subtotal_amount ?? $sale->total_amount) }}</span>
                    </div>
                    @if ($sale->isPendingDiscount() && $sale->discount_requested_amount)
                        <div class="flex justify-between gap-2">
                            <span class="text-neutral-600">Remise demandée</span>
                            <span class="tabular-nums font-medium text-amber-800">− {{ \App\Support\Money::usd($sale->discount_requested_amount) }}</span>
                        </div>
                    @elseif ($sale->discount_amount && (float) $sale->discount_amount > 0)
                        <div class="flex justify-between gap-2">
                            <span class="text-neutral-600">Remise</span>
                            <span class="tabular-nums font-medium text-neutral-800">− {{ \App\Support\Money::usd($sale->discount_amount) }}</span>
                        </div>
                        @if ($sale->discountApprovedByUser)
                            <p class="text-[11px] text-neutral-500">Approuvée par {{ $sale->discountApprovedByUser->name }}@if ($sale->discount_approved_at) · {{ $sale->discount_approved_at->translatedFormat('d/m/Y H:i') }}@endif</p>
                        @endif
                    @endif
                    <div class="mt-2 border-t border-neutral-100 pt-2">
                        <p class="flex justify-between gap-2 font-semibold">
                            <span class="text-neutral-700">Total facture</span>
                            <span class="tabular-nums text-primary">{{ \App\Support\Money::usd($expectedAmount) }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
