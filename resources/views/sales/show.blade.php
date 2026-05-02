<x-app-layout>
    <x-slot name="header">Vente {{ $sale->reference }}</x-slot>

    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Facturation</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">Vente {{ $sale->reference }}</h1>
            <p class="mt-2 text-sm text-neutral-600">
                Session #{{ $salesSession->id }} · {{ $sale->session->branch->name }} · {{ $sale->sold_at->translatedFormat('d/m/Y à H:i') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sales.print-large', [$salesSession, $sale]) }}" target="_blank" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Imprimer facture A4</a>
            <a href="{{ route('sales.print-small', [$salesSession, $sale]) }}" target="_blank" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Imprimer ticket POS</a>
            <a href="{{ route('sales-sessions.show', $salesSession) }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Retour à la session</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif

    @if ($errors->has('sale'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first('sale') }}</div>
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
            <p class="mt-2 text-xs text-amber-900/90">Le total enregistré pour la session reste le sous-total jusqu’à décision d’un administrateur.</p>
            @if (auth()->user()->is_admin)
                <div class="mt-4 flex flex-wrap gap-2">
                    <form action="{{ route('sales.approve-discount', [$salesSession, $sale]) }}" method="POST" onsubmit="return confirm('Approuver cette remise ?');">
                        @csrf
                        <x-primary-button type="submit">Approuver la remise</x-primary-button>
                    </form>
                    <form action="{{ route('sales.reject-discount', [$salesSession, $sale]) }}" method="POST" onsubmit="return confirm('Refuser la remise ? La vente sera confirmée sans remise.');">
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
                </table>
            </div>
        </section>

        <aside class="h-fit rounded-2xl border border-neutral-200 bg-gradient-to-b from-white to-neutral-50 p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Résumé</h2>
            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Paiement</p>
                    <p class="mt-1">
                        @if ($sale->payment_type === 'credit')
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Crédit</span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Cash</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Client</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $sale->client?->name ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3 space-y-2 text-sm">
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
                </div>
                <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
                    <p class="text-xs text-neutral-600">Total facture</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($sale->total_amount) }}</p>
                    @if ($sale->isPendingDiscount())
                        <p class="mt-2 text-xs text-neutral-600">Après approbation : {{ \App\Support\Money::usd(max(0, (float) ($sale->subtotal_amount ?? 0) - (float) ($sale->discount_requested_amount ?? 0))) }}</p>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
