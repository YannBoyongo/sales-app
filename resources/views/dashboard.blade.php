<x-app-layout>
    <x-slot name="header">
        Tableau de bord
    </x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Accueil</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    Bonjour, {{ auth()->user()->name }}
                </h1>
                <p class="mt-3 max-w-3xl text-base leading-relaxed text-neutral-600">
                    @if ($isAdmin)
                        Vous voyez les indicateurs sur <span class="font-medium text-neutral-800">toutes les branches</span>.
                        Gérez la structure (branches, départements, utilisateurs), les clients crédit, la comptabilité et les paramètres boutique.
                        @if ($branchesCount !== null)
                            <span class="text-neutral-500">— {{ $branchesCount }} branche{{ $branchesCount > 1 ? 's' : '' }}.</span>
                        @endif
                    @elseif ($isAccountant)
                        Vue <span class="font-medium text-neutral-800">finances</span> sur toutes les branches : clients (crédit), comptabilité et indicateurs agrégés.
                        Les réglages structurels et la gestion des utilisateurs restent réservés aux administrateurs.
                    @else
                        Espace <span class="font-medium text-neutral-800">point de vente et stock</span>
                        @if ($userBranch)
                            pour <span class="font-medium text-neutral-800">{{ $userBranch->name }}</span>
                        @endif
                        : ventes, stocks et produits accessibles à votre branche.
                        La comptabilité et les clients (dette) sont accessibles aux administrateurs et aux comptables.
                    @endif
                </p>
            </div>
        </x-slot>

        <div class="space-y-8">
            @if ($isAdmin && $pendingDiscountCount > 0)
                <div class="flex flex-col gap-3 rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-amber-100/20 px-4 py-4 text-sm text-amber-950 shadow-lg shadow-amber-900/5 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 text-xs font-bold text-amber-900" aria-hidden="true">%</span>
                        <div>
                            <p class="font-semibold text-amber-900">Remises en attente d’approbation</p>
                            <p class="mt-0.5 text-amber-800/90">
                                {{ $pendingDiscountCount }} vente{{ $pendingDiscountCount > 1 ? 's' : '' }} avec une remise demandée par la caisse — validez ou refusez sur la fiche vente.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('sales.overview', ['remise' => 1]) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2">
                        Voir les ventes
                    </a>
                </div>
            @endif

            @if ($lowStocksCount > 0)
                <div class="flex flex-col gap-3 rounded-2xl border border-red-200/80 bg-gradient-to-br from-red-50 to-red-100/20 px-4 py-4 text-sm text-red-950 shadow-lg shadow-red-900/5 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-200 text-xs font-bold text-red-900" aria-hidden="true">!</span>
                        <div>
                            <p class="font-semibold text-red-900">Stock sous le minimum</p>
                            <p class="mt-0.5 text-red-800/90">
                                {{ $lowStocksCount }} ligne{{ $lowStocksCount > 1 ? 's' : '' }} produit / emplacement
                                @if (! $seesAllBranches)
                                    sur votre périmètre
                                @endif
                                nécessitent un réapprovisionnement ou un transfert.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('stocks.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                        Voir la matrice des stocks
                    </a>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-5 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ventes (7 jours)</p>
                    <p class="mt-2 text-3xl font-semibold text-primary">{{ $weekSalesCount }}</p>
                    <p class="mt-1 text-sm text-neutral-600">Sur votre périmètre</p>
                </div>
                <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-5 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ventes aujourd’hui</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($todaySalesTotal) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">
                        {{ $todaySalesCount }} vente{{ $todaySalesCount > 1 ? 's' : '' }}
                        · Cash {{ \App\Support\Money::usd($todayCashTotal) }}
                        · Crédit {{ \App\Support\Money::usd($todayCreditTotal) }}
                    </p>
                </div>
                <div class="rounded-2xl border p-5 shadow-lg ring-1 backdrop-blur-sm @if ($lowStocksCount > 0) border-red-200/90 bg-red-50/90 shadow-red-900/5 ring-red-900/10 @else border-neutral-200/90 bg-white/90 shadow-neutral-900/5 ring-neutral-900/5 @endif">
                    <p class="text-xs font-medium uppercase tracking-wide @if ($lowStocksCount > 0) text-red-800 @else text-neutral-500 @endif">Alertes stock</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums @if ($lowStocksCount > 0) text-red-700 @else text-primary @endif">{{ $lowStocksCount }}</p>
                    <p class="mt-1 text-sm @if ($lowStocksCount > 0) font-medium text-red-900 @else text-neutral-600 @endif">
                        @if ($lowStocksCount > 0)
                            Sous le seuil — action requise
                        @else
                            Aucune alerte @if (! $seesAllBranches) (votre branche) @endif
                        @endif
                    </p>
                    <a href="{{ route('stocks.index') }}" class="mt-2 inline-block text-sm font-medium @if ($lowStocksCount > 0) text-red-800 underline decoration-red-300 hover:text-red-950 @else text-primary hover:underline @endif">Stocks →</a>
                </div>
                @if ($canAccessAccounting)
                    <div class="rounded-2xl border border-primary/25 bg-primary/[0.06] p-5 shadow-lg shadow-primary/10 ring-1 ring-primary/10 backdrop-blur-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-600">Caisse comptable (cumul)</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($accountingCaisse) }}</p>
                        <p class="mt-1 text-sm text-neutral-600">Débit − crédit (toutes écritures)</p>
                        <a href="{{ route('accounting.index') }}" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Comptabilité →</a>
                    </div>
                @else
                    <div class="rounded-2xl border border-neutral-200/90 bg-white/90 p-5 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Produits (périmètre)</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ $productsCount }}</p>
                        <p class="mt-1 text-sm text-neutral-600">{{ $seesAllBranches ? 'Vue agrégée (toutes branches)' : 'Liés à votre branche' }}</p>
                        <a href="{{ route('products.index') }}" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Produits →</a>
                    </div>
                @endif
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-neutral-200/90 bg-white/90 p-5 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Opérationnel</h2>
                    <p class="mt-1 text-xs text-neutral-500">Ventes, stock et achats selon vos droits.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('sales.overview') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Liste des ventes</a>
                        <a href="{{ route('sales.entry') }}" class="rounded-lg border border-primary/40 bg-primary/10 px-3 py-1.5 text-sm font-medium text-primary shadow-sm hover:bg-primary/15">Nouvelle vente</a>
                        <a href="{{ route('stocks.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Stocks</a>
                        <a href="{{ route('products.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Produits</a>
                        @if (auth()->user()->canManageApplication())
                            <a href="{{ route('branches.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Branches</a>
                        @endif
                        <a href="{{ route('stock-movements.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Mouvements</a>
                        <a href="{{ route('purchase-orders.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Bons de commande</a>
                        @if ($isAdmin)
                            <a href="{{ route('purchase-orders.create') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Nouveau bon de commande</a>
                        @endif
                    </div>
                </section>

                @if ($canAccessAccounting || $isAdmin)
                    <section class="rounded-2xl border border-neutral-200/90 bg-white/90 p-5 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                        @if ($canAccessAccounting)
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Finances</h2>
                            <p class="mt-1 text-xs text-neutral-500">Clients au crédit et grand livre.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('accounting.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Comptabilité</a>
                                <a href="{{ route('clients.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Clients</a>
                            </div>
                        @endif
                        @if ($isAdmin)
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500 {{ $canAccessAccounting ? 'mt-6' : '' }}">Configuration</h2>
                            <p class="mt-1 text-xs text-neutral-500">Structure boutique et comptes applicatifs.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('parametre.edit') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Paramètre boutique</a>
                                <a href="{{ route('branches.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Branches</a>
                                <a href="{{ route('departments.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Départements</a>
                                <a href="{{ route('users.index') }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm shadow-sm hover:bg-neutral-50">Utilisateurs</a>
                            </div>
                        @endif
                    </section>
                @endif
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <section class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-lg shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 bg-neutral-50/80 px-5 py-4">
                        <div>
                            <h2 class="font-semibold text-neutral-900">Dernières ventes</h2>
                            <p class="mt-0.5 text-xs text-neutral-500">Les enregistrements les plus récents sur votre périmètre</p>
                        </div>
                        <a href="{{ route('sales.overview') }}" class="text-sm font-medium text-neutral-600 hover:text-primary">Tout voir</a>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @forelse ($recentSales as $sale)
                            <li class="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-neutral-50/80">
                                <div class="min-w-0">
                                    <p class="truncate font-mono font-medium text-neutral-900">{{ $sale->reference }}</p>
                                    @if ($seesAllBranches)
                                        <p class="text-xs text-neutral-500">{{ $sale->branch->name }}</p>
                                    @endif
                                    <p class="text-xs text-neutral-500">{{ $sale->sold_at->translatedFormat('d M Y, H:i') }}</p>
                                    @if ($sale->user)
                                        <p class="text-xs text-neutral-500">Par {{ $sale->user->name }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('sales.show', [$sale->branch, $sale]) }}" class="shrink-0 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:opacity-95">Ouvrir</a>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-neutral-500">Aucune vente récente.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="overflow-hidden rounded-2xl border bg-white/90 shadow-lg ring-1 backdrop-blur-sm @if ($lowStocksCount > 0) border-red-200/90 shadow-red-900/5 ring-red-900/10 @else border-neutral-200/90 shadow-neutral-900/5 ring-neutral-900/5 @endif">
                    <div class="flex items-center justify-between border-b px-5 py-4 @if ($lowStocksCount > 0) border-red-100 bg-red-50/80 @else border-neutral-100 bg-neutral-50/80 @endif">
                        <div>
                            <h2 class="font-semibold @if ($lowStocksCount > 0) text-red-900 @else text-neutral-900 @endif">Stocks bas</h2>
                            @if ($lowStocksCount > 0)
                                <p class="mt-0.5 text-xs font-medium text-red-800">Produits sous le seuil (emplacement ou produit)</p>
                            @endif
                        </div>
                        <a href="{{ route('stocks.index') }}" class="text-sm @if ($lowStocksCount > 0) font-medium text-red-800 hover:text-red-950 @else font-medium text-neutral-600 hover:text-primary @endif">Stocks</a>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @forelse ($lowStocks as $stock)
                            @php
                                $seuil = $stock->minimum_stock ?? $stock->product->minimum_stock;
                            @endphp
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
                                    @if ($stock->minimum_stock !== null && $stock->product->minimum_stock !== null && (int) $stock->minimum_stock !== (int) $stock->product->minimum_stock)
                                        <span class="text-neutral-500">(empl.)</span>
                                    @elseif ($stock->minimum_stock === null && $stock->product->minimum_stock !== null)
                                        <span class="text-neutral-500">(seuil produit)</span>
                                    @endif
                                </p>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-neutral-500">Aucune alerte — tous les stocks suivis sont au-dessus du minimum.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </x-caisse-flow>
</x-app-layout>
