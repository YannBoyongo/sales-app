<x-app-layout>
    <x-slot name="header">Actions en attente</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div>
                <p class="app-page-eyebrow">Suivi</p>
                <h1 class="app-page-title">Actions en attente</h1>
                <p class="app-page-desc max-w-3xl">
                    Centralisez les validations et alertes opérationnelles visibles depuis le tableau de bord.
                </p>
            </div>
        </x-slot>

        <div class="space-y-4">
            @if ($isAdmin && $pendingDiscountCount > 0)
                <div class="flex flex-col gap-3 app-alert-warning sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 text-xs font-bold text-amber-900" aria-hidden="true">%</span>
                        <div>
                            <p class="font-semibold text-amber-900">Remises en attente d’approbation</p>
                            <p class="mt-0.5 text-amber-800/90">
                                {{ $pendingDiscountCount }} vente{{ $pendingDiscountCount > 1 ? 's' : '' }} nécessitent une décision administrateur.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('sales.overview', ['remise' => 1]) }}" class="app-btn-primary shrink-0 bg-amber-800 hover:bg-amber-900">
                        Voir les ventes
                    </a>
                </div>
            @endif

            @if ($isAdmin && $pendingReceptionBatchCount > 0)
                <div class="flex flex-col gap-3 app-alert-warning sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 text-xs font-bold text-amber-900" aria-hidden="true">BC</span>
                        <div>
                            <p class="font-semibold text-amber-900">Réceptions de bons de commande en attente</p>
                            <p class="mt-0.5 text-amber-800/90">
                                {{ $pendingReceptionBatchCount }} réception{{ $pendingReceptionBatchCount > 1 ? 's' : '' }} soumise{{ $pendingReceptionBatchCount > 1 ? 's' : '' }} — validez ou refusez pour mettre à jour le stock.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('purchase-orders.index') }}" class="app-btn-primary shrink-0 bg-amber-800 hover:bg-amber-900">
                        Voir les bons de commande
                    </a>
                </div>
            @endif

            @if ($lowStocksCount > 0)
                <div class="flex flex-col gap-3 app-alert-danger sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-200 text-xs font-bold text-red-900" aria-hidden="true">!</span>
                        <div>
                            <p class="font-semibold text-red-900">Stock sous le minimum</p>
                            <p class="mt-0.5 text-red-800/90">
                                {{ $lowStocksCount }} ligne{{ $lowStocksCount > 1 ? 's' : '' }} produit / emplacement
                                @if (! $seesAllBranches)
                                    sur votre périmètre
                                @endif
                                nécessitent une action.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('stocks.index') }}" class="app-btn-primary shrink-0 bg-red-700 hover:bg-red-800">
                        Voir la matrice des stocks
                    </a>
                </div>
            @endif

            @if (($isAdmin && $pendingDiscountCount === 0 && $pendingReceptionBatchCount === 0) && $lowStocksCount === 0)
                <div class="app-alert-success">
                    <p class="font-semibold">Aucune action en attente.</p>
                    <p class="mt-1 text-emerald-800/90">Tout est à jour pour votre périmètre.</p>
                </div>
            @endif
        </div>

        @if ($lowStocksCount > 0)
            <section class="app-panel mt-8">
                <div class="app-panel-header border-red-100 bg-red-50/80">
                    <div>
                        <h2 class="font-semibold text-red-900">Stocks bas</h2>
                        <p class="mt-0.5 text-xs font-medium text-red-800">8 premières lignes sous le seuil</p>
                    </div>
                    <a href="{{ route('stocks.index') }}" class="text-sm font-medium text-red-800 hover:text-red-950">Stocks</a>
                </div>
                <ul class="divide-y divide-neutral-100">
                    @foreach ($lowStocks as $stock)
                        @php($seuil = $stock->minimum_stock ?? $stock->product->minimum_stock)
                        <li class="border-l-4 border-red-500 bg-red-50/40 px-5 py-3">
                            <p class="font-medium text-neutral-900">{{ $stock->product->name }}</p>
                            <p class="text-sm text-neutral-600">
                                {{ $stock->location->name }}
                                @if ($seesAllBranches)
                                    <span class="text-neutral-400">({{ $stock->location->branch->name }})</span>
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-red-900/90">
                                Qté actuelle : <span class="font-semibold tabular-nums">{{ $stock->quantity }}</span>
                                — Seuil : <span class="font-semibold tabular-nums">{{ $seuil }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </x-caisse-flow>
</x-app-layout>
