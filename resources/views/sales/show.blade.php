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
            <p class="app-page-eyebrow">Facturation</p>
            <h1 class="app-page-title">Vente {{ $sale->reference }}</h1>
            <p class="app-page-desc">
                {{ $branch->name }} · {{ $sale->effectiveSoldAt()->translatedFormat('d/m/Y') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sales.print-large', [$branch, $sale]) }}" target="_blank" class="app-btn-secondary">Imprimer facture A4</a>
            <a href="{{ route('sales.print-small', [$branch, $sale]) }}" target="_blank" class="app-btn-secondary">Imprimer ticket POS</a>
            @if (auth()->user()?->isAdmin())
                <form
                    action="{{ route('sales.destroy', [$branch, $sale]) }}"
                    method="POST"
                    class="inline-flex"
                    onsubmit="return confirm('Supprimer définitivement cette vente ? Le stock sera réintégré sur les emplacements concernés.');"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="app-btn-danger">
                        Supprimer la vente
                    </button>
                </form>
            @endif
            <a href="{{ $backToSalesListUrl }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Liste des ventes</a>
        </div>
    </div>

    @if (session('success'))
        <div class="app-alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->has('sale'))
        <div class="app-alert-danger" role="alert">{{ $errors->first('sale') }}</div>
    @endif
    @if ($errors->has('sale_payment'))
        <div class="app-alert-danger" role="alert">{{ $errors->first('sale_payment') }}</div>
    @endif

    @if ($sale->isPendingDiscount())
        <div class="app-alert-warning">
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
            <p class="mt-2 text-xs opacity-90">Le total enregistré reste au prix catalogue jusqu’à décision d’un administrateur. Après approbation, les prix négociés sont confirmés.</p>
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
        <section class="app-panel app-panel-body">
            <h2 class="text-lg font-semibold text-neutral-900">Produits vendus</h2>
            <div class="app-table-shell mt-4 -mx-4 sm:-mx-5 lg:-mx-6 border-0 shadow-none">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="py-3 pr-4">Produit</th>
                            <th class="py-3 pr-4">Emplacement</th>
                            <th class="py-3 pr-4 text-right">Qté</th>
                            <th class="py-3 pr-4 text-right">PU</th>
                            <th class="py-3 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->items as $item)
                            <tr>
                                <td class="font-medium text-neutral-900">{{ $item->product->name }}</td>
                                <td>{{ $item->location->name }}</td>
                                <td class="text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="text-right tabular-nums">{{ \App\Support\Money::usd($item->unit_price) }}</td>
                                <td class="text-right tabular-nums">{{ \App\Support\Money::usd($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50/60">
                        <tr>
                            <th colspan="4" scope="row" class="text-left text-xs font-semibold uppercase tracking-wide">
                                Montant à payer
                            </th>
                            <td class="text-right tabular-nums font-semibold text-neutral-900">
                                {{ \App\Support\Money::usd($sale->subtotal_amount ?? $sale->total_amount) }}
                            </td>
                        </tr>
                        @if (($sale->isPendingDiscount() && (float) ($sale->discount_requested_amount ?? 0) > 0) || ((float) ($sale->discount_amount ?? 0) > 0))
                            <tr>
                                <th colspan="4" scope="row" class="text-left text-xs font-semibold uppercase tracking-wide">
                                    Remise
                                </th>
                                <td class="text-right tabular-nums font-medium text-amber-800">
                                    − {{ \App\Support\Money::usd($sale->isPendingDiscount() ? ($sale->discount_requested_amount ?? 0) : ($sale->discount_amount ?? 0)) }}
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="4" scope="row" class="text-left text-sm font-semibold">
                                Nouveau total
                            </th>
                            <td class="text-right tabular-nums text-base font-bold text-primary">
                                {{ \App\Support\Money::usd($expectedAmount) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <aside class="app-panel app-panel-body h-fit">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Résumé</h2>
            <div class="mt-4 space-y-3">
                @if ($sale->posShift && $sale->posShift->posTerminal)
                    <div class="app-stat-card !p-4 !shadow-none">
                        <p class="text-xs text-neutral-500">Session POS</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $sale->posShift->posTerminal->name }}</p>
                        <p class="mt-1 text-xs text-neutral-500">
                            Session du {{ $sale->posShift->effectiveSessionDate()->translatedFormat('d/m/Y') }}
                            @if ($sale->posShift->closed_at)
                                · fermée {{ $sale->posShift->closed_at->translatedFormat('d/m/Y H:i') }}
                            @endif
                        </p>
                    </div>
                @endif
                <div class="app-stat-card !p-4 !shadow-none">
                    <p class="text-xs text-neutral-500">Client</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $sale->displayClientName() ?? '—' }}</p>
                    @if ($sale->displayClientPhone())
                        <p class="mt-1 text-sm text-neutral-600">{{ $sale->displayClientPhone() }}</p>
                    @endif
                </div>
                <div class="app-stat-card !p-4 !shadow-none space-y-2 text-sm">
                    <p class="text-xs text-neutral-500">Mode de paiement</p>
                    <p>
                        @if ($sale->payment_type === 'credit')
                            <span class="app-badge-warning">Crédit</span>
                        @elseif ($sale->payment_type === 'caution')
                            <span class="app-badge-info">Caution</span>
                        @else
                            <span class="app-badge-success">Cash</span>
                        @endif
                    </p>
                    <p class="text-xs text-neutral-500 pt-1">Statut paiement</p>
                    <p>
                        @if ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_NOT_PAID)
                            <span class="app-badge-danger">Non payé</span>
                        @elseif ($effectiveStatus === \App\Models\Sale::PAYMENT_STATUS_PARTIALLY_PAID)
                            <span class="app-badge-warning">Partiellement payé</span>
                        @else
                            <span class="app-badge-success">Entièrement payé</span>
                        @endif
                    </p>
                    <div class="border-t border-neutral-100 pt-2">
                        <p class="flex justify-between gap-2"><span class="text-neutral-600">Montant à payer</span><span class="tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($expectedAmount) }}</span></p>
                        <p class="mt-1 flex justify-between gap-2"><span class="text-neutral-600">Montant payé</span><span class="tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($paidAmount) }}</span></p>
                        <p class="mt-1 flex justify-between gap-2"><span class="text-neutral-600">Reste à payer</span><span class="tabular-nums font-semibold {{ (float) $remainingAmount > 0 ? 'text-amber-800' : 'text-emerald-700' }}">{{ \App\Support\Money::usd($remainingAmount) }}</span></p>
                    </div>
                </div>
                <div class="app-stat-card !p-4 !shadow-none space-y-3 text-sm">
                    <p class="text-xs text-neutral-500">Confirmation de règlement</p>
                    @if ($sale->isPendingDiscount())
                        <p class="text-xs text-amber-800">Paiement indisponible pendant qu’une remise est en attente d’approbation.</p>
                    @elseif ((float) $remainingAmount <= 0)
                        <p class="text-xs text-emerald-700">Cette vente est déjà soldée.</p>
                    @else
                        <form action="{{ route('sales.confirm-paid', [$branch, $sale]) }}" method="POST" onsubmit="return confirm('Confirmer cette vente comme entièrement payée ?');">
                            @csrf
                            <button type="submit" class="app-btn-primary w-full justify-center">
                                Confirmer cette vente comme payée
                            </button>
                        </form>
                    @endif
                </div>
                <div class="app-stat-card !p-4 !shadow-none space-y-2 text-sm">
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
