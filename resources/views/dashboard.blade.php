<x-app-layout>
    <x-slot name="header">
        Tableau de bord
    </x-slot>

    <div class="space-y-8">
        <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-neutral-900">
                        Bonjour, {{ auth()->user()->name }}
                    </h1>
                    <p class="mt-2 text-sm text-neutral-600 leading-relaxed">
                        @if ($isAdmin)
                            Vous voyez les indicateurs sur <span class="font-medium text-neutral-800">toutes les branches</span>.
                            Gérez la structure (branches, départements, utilisateurs), les clients crédit, la comptabilité et les paramètres boutique.
                            @if ($branchesCount !== null)
                                <span class="text-neutral-500">— {{ $branchesCount }} branche{{ $branchesCount > 1 ? 's' : '' }}.</span>
                            @endif
                        @else
                            Espace <span class="font-medium text-neutral-800">point de vente et stock</span>
                            @if ($userBranch)
                                pour <span class="font-medium text-neutral-800">{{ $userBranch->name }}</span>
                            @endif
                            : sessions journalières, ventes, dépenses de session, stocks et produits accessibles à votre branche.
                            La comptabilité, les clients (dette) et les paramètres sont réservés aux administrateurs.
                        @endif
                    </p>
                </div>
            </div>

            @if ($isAdmin && $openSessionsByBranch !== null && $openSessionsByBranch->isNotEmpty())
                <div class="mt-5 flex flex-wrap gap-2 border-t border-neutral-100 pt-5">
                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Sessions ouvertes par branche</span>
                    @foreach ($openSessionsByBranch as $row)
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900 dashboard-open-session-blink">
                            {{ $row['branch_name'] }}
                            <span class="tabular-nums text-amber-800">({{ $row['count'] }})</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($lowStocksCount > 0)
            <div class="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-950 shadow-sm sm:flex-row sm:items-center sm:justify-between" role="status">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-200 text-xs font-bold text-red-900" aria-hidden="true">!</span>
                    <div>
                        <p class="font-semibold text-red-900">Stock sous le minimum</p>
                        <p class="mt-0.5 text-red-800/90">
                            {{ $lowStocksCount }} ligne{{ $lowStocksCount > 1 ? 's' : '' }} produit / emplacement
                            @if (! $isAdmin)
                                sur votre périmètre
                            @endif
                            nécessitent un réapprovisionnement ou un transfert.
                        </p>
                    </div>
                </div>
                <a href="{{ route('stocks.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                    Voir la matrice des stocks
                </a>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm @if ($openSessionsCount > 0) ring-2 ring-amber-200/80 @endif">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Sessions ouvertes</p>
                <p class="mt-2 text-3xl font-semibold text-primary @if ($openSessionsCount > 0) dashboard-open-session-count-blink @endif">{{ $openSessionsCount }}</p>
                <p class="mt-1 text-sm text-neutral-600">À clôturer quand la journée est terminée</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ventes aujourd’hui</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($todaySalesTotal) }}</p>
                <p class="mt-1 text-xs text-neutral-500">
                    {{ $todaySalesCount }} vente{{ $todaySalesCount > 1 ? 's' : '' }}
                    · Cash {{ \App\Support\Money::usd($todayCashTotal) }}
                    · Crédit {{ \App\Support\Money::usd($todayCreditTotal) }}
                </p>
            </div>
            <div class="rounded-lg border p-5 shadow-sm @if ($lowStocksCount > 0) border-red-300 bg-red-50 ring-2 ring-red-200/90 @else border-neutral-200 bg-white @endif">
                <p class="text-xs font-medium uppercase tracking-wide @if ($lowStocksCount > 0) text-red-800 @else text-neutral-500 @endif">Alertes stock</p>
                <p class="mt-2 text-3xl font-semibold tabular-nums @if ($lowStocksCount > 0) text-red-700 @else text-primary @endif">{{ $lowStocksCount }}</p>
                <p class="mt-1 text-sm @if ($lowStocksCount > 0) font-medium text-red-900 @else text-neutral-600 @endif">
                    @if ($lowStocksCount > 0)
                        Sous le seuil — action requise
                    @else
                        Aucune alerte @if (! $isAdmin) (votre branche) @endif
                    @endif
                </p>
                <a href="{{ route('stocks.index') }}" class="mt-2 inline-block text-sm font-medium @if ($lowStocksCount > 0) text-red-800 underline decoration-red-300 hover:text-red-950 @else text-primary hover:underline @endif">Stocks →</a>
            </div>
            @if ($isAdmin)
                <div class="rounded-lg border border-primary/20 bg-primary/5 p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-600">Caisse comptable (cumul)</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($accountingCaisse) }}</p>
                    <p class="mt-1 text-sm text-neutral-600">Débit − crédit (toutes écritures)</p>
                    <a href="{{ route('accounting.index') }}" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Comptabilité →</a>
                </div>
            @else
                <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Produits (périmètre)</p>
                    <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ $productsCount }}</p>
                    <p class="mt-1 text-sm text-neutral-600">Liés à votre branche</p>
                    <a href="{{ route('products.index') }}" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Produits →</a>
                </div>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Opérationnel</h2>
                <p class="mt-1 text-xs text-neutral-500">Ventes, stock et achats selon vos droits.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('sales-sessions.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Ventes journalières</a>
                    <a href="{{ route('sales-sessions.create') }}" class="rounded-md border border-primary/40 bg-primary/10 px-3 py-1.5 text-sm font-medium text-primary hover:bg-primary/15">Nouvelle session / vente</a>
                    <a href="{{ route('stocks.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Stocks</a>
                    <a href="{{ route('products.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Produits</a>
                    <a href="{{ route('locations.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Emplacements</a>
                    <a href="{{ route('stock-movements.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Mouvements</a>
                    <a href="{{ route('purchase-orders.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Bons de commande</a>
                    @if ($isAdmin)
                        <a href="{{ route('purchase-orders.create') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Nouveau bon de commande</a>
                    @endif
                </div>
            </section>

            @if ($isAdmin)
                <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Administration</h2>
                    <p class="mt-1 text-xs text-neutral-500">Structure, clients crédit, comptabilité et réglages boutique.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('accounting.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Comptabilité</a>
                        <a href="{{ route('clients.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Clients</a>
                        <a href="{{ route('parametre.edit') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Paramètre boutique</a>
                        <a href="{{ route('branches.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Branches</a>
                        <a href="{{ route('departments.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Départements</a>
                        <a href="{{ route('users.index') }}" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm hover:bg-neutral-50">Utilisateurs</a>
                    </div>
                </section>
            @endif
        </div>

        <div class="grid gap-8 lg:grid-cols-2">
            <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-200 bg-neutral-50 px-5 py-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-neutral-900">Sessions ouvertes</h2>
                        @if ($openSessionsCount > 0)
                            <p class="mt-0.5 text-xs font-medium text-amber-800">Pensez à la clôture et aux dépenses de session</p>
                        @endif
                    </div>
                    <a href="{{ route('sales-sessions.index') }}" class="text-sm text-neutral-600 hover:text-primary">Tout voir</a>
                </div>
                <ul class="divide-y divide-neutral-100">
                    @forelse ($openSessions as $session)
                        <li class="px-5 py-3 flex items-center justify-between gap-4 transition-colors dashboard-open-session-blink">
                            <div class="min-w-0">
                                @if ($isAdmin)
                                    <p class="font-medium text-neutral-900 truncate">{{ $session->branch->name }}</p>
                                    <p class="text-xs text-neutral-500">Session #{{ $session->id }}</p>
                                @else
                                    <p class="font-medium text-neutral-900 truncate">Session #{{ $session->id }}</p>
                                    @if ($session->branch)
                                        <p class="text-xs text-neutral-500">{{ $session->branch->name }}</p>
                                    @endif
                                @endif
                                <p class="text-xs text-neutral-500">Ouverte le {{ $session->opened_at->translatedFormat('d M Y, H:i') }}</p>
                                @if ($session->opener)
                                    <p class="text-xs text-neutral-500">Par {{ $session->opener->name }}</p>
                                @endif
                            </div>
                            <a href="{{ route('sales-sessions.show', $session) }}" class="shrink-0 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:opacity-95">Ouvrir</a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-neutral-500">Aucune session ouverte.</li>
                    @endforelse
                </ul>
            </section>

            <section class="overflow-hidden rounded-lg border shadow-sm @if ($lowStocksCount > 0) border-red-200 @else border-neutral-200 @endif bg-white">
                <div class="flex items-center justify-between border-b px-5 py-3 @if ($lowStocksCount > 0) border-red-100 bg-red-50/80 @else border-neutral-200 bg-neutral-50 @endif">
                    <div>
                        <h2 class="font-semibold @if ($lowStocksCount > 0) text-red-900 @else text-neutral-900 @endif">Stocks bas</h2>
                        @if ($lowStocksCount > 0)
                            <p class="mt-0.5 text-xs font-medium text-red-800">Produits sous le seuil (emplacement ou produit)</p>
                        @endif
                    </div>
                    <a href="{{ route('stocks.index') }}" class="text-sm @if ($lowStocksCount > 0) font-medium text-red-800 hover:text-red-950 @else text-neutral-600 hover:text-primary @endif">Stocks</a>
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
                                @if ($isAdmin)
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
</x-app-layout>
