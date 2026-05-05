<x-app-layout>
    <x-slot name="header">Vente {{ $saleItem->reference ?? ('SALE-'.$saleItem->id) }}</x-slot>

    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Détail de vente</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">Vente {{ $saleItem->reference ?? ('SALE-'.$saleItem->id) }}</h1>
            <p class="mt-2 text-sm text-neutral-600">
                {{ $branch->name }} · {{ $saleItem->created_at->translatedFormat('d/m/Y à H:i') }}
            </p>
        </div>
        <a href="{{ route('sales.overview') }}" class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm hover:border-primary/30 hover:text-primary">
            <span aria-hidden="true">←</span>
            Liste des ventes
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-neutral-900">Informations principales</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Produit</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $saleItem->product->name }}</p>
                    <p class="mt-1 text-xs text-neutral-600">{{ $saleItem->product->department?->name ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Emplacement</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $saleItem->location->name }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Vendu par</p>
                    <p class="mt-1 font-semibold text-neutral-900">{{ $saleItem->user?->name ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Client</p>
                    @if ($saleItem->client)
                        <a class="mt-1 inline-block font-semibold text-primary hover:underline" href="{{ route('clients.show', $saleItem->client) }}">{{ $saleItem->client->name }}</a>
                    @else
                        <p class="mt-1 font-semibold text-neutral-900">—</p>
                    @endif
                </div>
            </div>
        </section>

        <aside class="h-fit rounded-2xl border border-neutral-200 bg-gradient-to-b from-white to-neutral-50 p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-neutral-500">Résumé financier</h2>
            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Mode de paiement</p>
                    <p class="mt-1">
                        @if ($saleItem->payment_type === 'credit')
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Crédit</span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Cash</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Quantité</p>
                    <p class="mt-1 font-semibold tabular-nums text-neutral-900">{{ $saleItem->quantity }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Prix unitaire</p>
                    <p class="mt-1 font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($saleItem->unit_price) }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs text-neutral-500">Remise</p>
                    <p class="mt-1 font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($saleItem->discount_amount ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
                    <p class="text-xs text-neutral-600">Montant final</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($saleItem->line_total) }}</p>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
