<x-app-layout>
    <x-slot name="header">
        Tableau de bord
    </x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="dashboard-hero">
                <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Accueil</p>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            Bonjour, {{ auth()->user()->name }}
                        </h1>
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-white/85 sm:text-base">
                            @if ($isAdmin)
                                Vous voyez les indicateurs sur <span class="font-semibold text-white">toutes les branches</span>.
                                Gérez la structure (branches, départements, utilisateurs), les clients crédit, la comptabilité et les paramètres boutique.
                                @if ($branchesCount !== null)
                                    <span class="text-white/70">- {{ $branchesCount }} branche{{ $branchesCount > 1 ? 's' : '' }}.</span>
                                @endif
                            @elseif ($isAccountant)
                                Vue <span class="font-semibold text-white">finances</span> sur toutes les branches : clients (crédit), comptabilité et indicateurs agrégés.
                                Les réglages structurels et la gestion des utilisateurs restent réservés aux administrateurs.
                            @else
                                Espace <span class="font-semibold text-white">point de vente et stock</span>
                                @if ($userBranch)
                                    pour <span class="font-semibold text-white">{{ $userBranch->name }}</span>
                                @endif
                                : ventes, stocks et produits accessibles à votre branche.
                                La comptabilité et les clients (dette) sont accessibles aux administrateurs et aux comptables.
                            @endif
                        </p>
                    </div>
                    @if ($userBranch)
                        <span class="inline-flex shrink-0 items-center rounded-full border border-white/25 bg-white/15 px-4 py-1.5 text-sm font-semibold text-white backdrop-blur-sm">
                            {{ $userBranch->name }}
                        </span>
                    @elseif ($isAdmin && $branchesCount !== null)
                        <span class="inline-flex shrink-0 items-center rounded-full border border-white/25 bg-white/15 px-4 py-1.5 text-sm font-semibold text-white backdrop-blur-sm">
                            {{ $branchesCount }} branche{{ $branchesCount > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
                <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-20 left-1/3 h-40 w-40 rounded-full bg-primary-light/35 blur-3xl" aria-hidden="true"></div>
            </div>
        </x-slot>

        <div class="space-y-8">
            @if ($isAdmin && $pendingDiscountCount > 0)
                <div class="flex flex-col gap-3 app-alert-warning sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 text-xs font-bold text-amber-900" aria-hidden="true">%</span>
                        <div>
                            <p class="font-semibold text-amber-900">Remises en attente d’approbation</p>
                            <p class="mt-0.5 text-amber-800/90">
                                {{ $pendingDiscountCount }} vente{{ $pendingDiscountCount > 1 ? 's' : '' }} avec une remise demandée par la caisse - validez ou refusez sur la fiche vente.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('sales.overview', ['remise' => 1]) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2">
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
                                {{ $pendingReceptionBatchCount }} réception{{ $pendingReceptionBatchCount > 1 ? 's' : '' }} soumise{{ $pendingReceptionBatchCount > 1 ? 's' : '' }} - approuvez ou refusez sur la fiche du bon de commande pour mettre à jour le stock.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('purchase-orders.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2">
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
                                nécessitent un réapprovisionnement ou un transfert.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('stocks.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                        Voir la matrice des stocks
                    </a>
                </div>
            @endif

            @if ($isAdmin && $monthlySalesTrend)
                <div class="space-y-6">
                    <div class="app-panel overflow-hidden border-primary/15">
                        <div class="dashboard-chart-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="font-semibold">Analyse des ventes</h2>
                                <p class="mt-0.5 text-xs text-white/70">
                                    {{ $monthlySalesTrend['month_label'] }} - {{ $monthlySalesTrend['total_count'] }} vente{{ $monthlySalesTrend['total_count'] > 1 ? 's' : '' }}
                                    · {{ \App\Support\Money::usd($monthlySalesTrend['total_amount']) }} (toutes branches)
                                </p>
                            </div>
                            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
                                <div>
                                    <label for="sales_month" class="block text-[11px] font-semibold uppercase tracking-wide text-white/70">Mois</label>
                                    <select id="sales_month" name="sales_month" class="mt-1 block rounded-lg border-white/30 bg-white px-2.5 py-1.5 text-sm text-primary-dark shadow-sm focus:border-white focus:ring-white/40" onchange="this.form.submit()">
                                        @foreach ($salesMonthOptions as $monthNumber => $monthLabel)
                                            <option value="{{ $monthNumber }}" @selected((int) $selectedSalesMonth === (int) $monthNumber)>{{ $monthLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="sales_year" class="block text-[11px] font-semibold uppercase tracking-wide text-white/70">Année</label>
                                    <select id="sales_year" name="sales_year" class="mt-1 block rounded-lg border-white/30 bg-white px-2.5 py-1.5 text-sm text-primary-dark shadow-sm focus:border-white focus:ring-white/40" onchange="this.form.submit()">
                                        @foreach ($salesYearOptions as $year)
                                            <option value="{{ $year }}" @selected((int) $selectedSalesYear === (int) $year)>{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="app-panel overflow-hidden border-primary/15">
                            <div class="dashboard-chart-header">
                                <h3 class="font-semibold">Ventes quotidiennes</h3>
                                <p class="mt-0.5 text-xs text-white/70">Évolution jour par jour sur le mois sélectionné</p>
                            </div>
                            <div class="app-panel-body space-y-4">
                                <div class="flex flex-wrap gap-x-6 gap-y-2 rounded-lg border border-primary/10 bg-primary-soft/60 px-3 py-2.5 text-xs text-neutral-600">
                                    <div class="flex items-start gap-2">
                                        <span class="mt-2 h-0.5 w-5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                                        <div>
                                            <p class="font-semibold text-neutral-800">Montant ($)</p>
                                            <p class="text-neutral-500">Total des ventes du jour - échelle à gauche</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="mt-2 h-0.5 w-5 shrink-0 border-t-2 border-dashed border-primary-light" aria-hidden="true"></span>
                                        <div>
                                            <p class="font-semibold text-neutral-800">Nombre de ventes</p>
                                            <p class="text-neutral-500">Quantité de tickets du jour - échelle à droite</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="mt-1.5 inline-flex h-4 w-5 shrink-0 items-center justify-center rounded bg-white text-[10px] font-semibold text-neutral-500 ring-1 ring-slate-200" aria-hidden="true">1–31</span>
                                        <div>
                                            <p class="font-semibold text-neutral-800">Jours du mois</p>
                                            <p class="text-neutral-500">Axe horizontal : chaque point = un jour de {{ $monthlySalesTrend['month_label'] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="relative h-72 w-full">
                                    <canvas
                                        id="monthly-sales-trend-chart"
                                        aria-label="Graphique des ventes quotidiennes du mois"
                                        role="img"
                                    ></canvas>
                                </div>
                            </div>
                        </section>

                        <section class="app-panel overflow-hidden border-primary/15">
                            <div class="dashboard-chart-header">
                                <h3 class="font-semibold">Ventes par branche</h3>
                                <p class="mt-0.5 text-xs text-white/70">Répartition des ventes du mois sélectionné</p>
                            </div>
                            <div class="app-panel-body space-y-4">
                                <div class="flex flex-wrap gap-x-6 gap-y-2 rounded-lg border border-primary/10 bg-primary-soft/60 px-3 py-2.5 text-xs text-neutral-600">
                                    <div class="flex items-start gap-2">
                                        <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                                        <div>
                                            <p class="font-semibold text-neutral-800">Parts du camembert</p>
                                            <p class="text-neutral-500">Chaque part = une branche, proportionnelle au montant des ventes</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="mt-1.5 inline-flex h-4 w-5 shrink-0 items-center justify-center rounded bg-white text-[10px] font-semibold text-neutral-500 ring-1 ring-slate-200" aria-hidden="true">%</span>
                                        <div>
                                            <p class="font-semibold text-neutral-800">Pourcentage</p>
                                            <p class="text-neutral-500">Part du montant total du mois ({{ $monthlySalesTrend['month_label'] }})</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="mt-1.5 inline-flex h-4 w-5 shrink-0 items-center justify-center rounded bg-white text-[10px] font-semibold text-neutral-500 ring-1 ring-slate-200" aria-hidden="true">i</span>
                                        <div>
                                            <p class="font-semibold text-neutral-800">Info-bulle</p>
                                            <p class="text-neutral-500">Survolez une part pour voir le montant, le % et le nombre de ventes</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="relative mx-auto h-72 w-full max-w-md">
                                    <canvas
                                        id="branch-sales-chart"
                                        aria-label="Graphique camembert des ventes par branche"
                                        role="img"
                                    ></canvas>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
                @push('scripts')
                    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (typeof Chart === 'undefined') {
                                return;
                            }

                            const money = new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            });

                            const trendCanvas = document.getElementById('monthly-sales-trend-chart');
                            if (trendCanvas) {
                                const trend = @json($monthlySalesTrend);
                                new Chart(trendCanvas, {
                                    type: 'line',
                                    data: {
                                        labels: trend.labels,
                                        datasets: [
                                            {
                                                label: 'Montant ($)',
                                                data: trend.amounts,
                                                borderColor: '#005EB8',
                                                backgroundColor: 'rgba(0, 94, 184, 0.12)',
                                                fill: true,
                                                tension: 0.35,
                                                pointRadius: 2,
                                                pointHoverRadius: 5,
                                                yAxisID: 'y',
                                            },
                                            {
                                                label: 'Nombre de ventes',
                                                data: trend.counts,
                                                borderColor: '#3379C6',
                                                backgroundColor: 'transparent',
                                                borderDash: [5, 4],
                                                tension: 0.35,
                                                pointRadius: 1.5,
                                                pointHoverRadius: 4,
                                                yAxisID: 'y1',
                                            },
                                        ],
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        interaction: { mode: 'index', intersect: false },
                                        plugins: {
                                            legend: { display: false },
                                            tooltip: {
                                                callbacks: {
                                                    title(items) {
                                                        const day = items[0]?.label ?? '';
                                                        return 'Jour ' + day + ' - ' + (trend.month_label || '');
                                                    },
                                                    label(context) {
                                                        const value = context.parsed.y;
                                                        if (context.dataset.yAxisID === 'y') {
                                                            return ' Montant : ' + money.format(value);
                                                        }
                                                        return ' Ventes : ' + value;
                                                    },
                                                },
                                            },
                                        },
                                        scales: {
                                            x: {
                                                grid: { display: false },
                                                ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 16 },
                                            },
                                            y: {
                                                position: 'left',
                                                beginAtZero: true,
                                                grid: { color: 'rgba(148, 163, 184, 0.25)' },
                                                ticks: {
                                                    callback(value) {
                                                        return money.format(value);
                                                    },
                                                },
                                            },
                                            y1: {
                                                position: 'right',
                                                beginAtZero: true,
                                                grid: { drawOnChartArea: false },
                                                ticks: { precision: 0 },
                                            },
                                        },
                                    },
                                });
                            }

                            const branchCanvas = document.getElementById('branch-sales-chart');
                            if (branchCanvas) {
                                const branchChart = @json($branchSalesChart);
                                const palette = [
                                    '#003D7A', '#005EB8', '#3379C6', '#004E99', '#005EB8',
                                    '#3379C6', '#003D7A', '#004E99', '#005EB8', '#3379C6',
                                ];
                                const entries = (branchChart.labels || []).map((label, index) => ({
                                    label,
                                    amount: Number(branchChart.amounts?.[index] ?? 0),
                                    count: Number(branchChart.counts?.[index] ?? 0),
                                })).filter((entry) => entry.amount > 0);

                                if (entries.length === 0) {
                                    entries.push({
                                        label: 'Aucune vente',
                                        amount: 1,
                                        count: 0,
                                        empty: true,
                                    });
                                }

                                const totalAmount = entries.reduce((sum, entry) => sum + (entry.empty ? 0 : entry.amount), 0);

                                new Chart(branchCanvas, {
                                    type: 'pie',
                                    data: {
                                        labels: entries.map((entry) => entry.label),
                                        datasets: [
                                            {
                                                label: 'Montant ($)',
                                                data: entries.map((entry) => entry.amount),
                                                backgroundColor: entries.map((entry, index) =>
                                                    entry.empty ? 'rgba(148, 163, 184, 0.35)' : palette[index % palette.length]
                                                ),
                                                borderColor: '#ffffff',
                                                borderWidth: 2,
                                            },
                                        ],
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    boxWidth: 12,
                                                    usePointStyle: true,
                                                    padding: 14,
                                                },
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    title(items) {
                                                        const branch = items[0]?.label ?? '';
                                                        return branch + ' - ' + (branchChart.month_label || '');
                                                    },
                                                    label(context) {
                                                        const entry = entries[context.dataIndex];
                                                        if (entry?.empty) {
                                                            return ' Aucune vente sur cette période';
                                                        }
                                                        const amount = Number(context.parsed || 0);
                                                        const percent = totalAmount > 0
                                                            ? ((amount / totalAmount) * 100).toFixed(1)
                                                            : '0.0';
                                                        return [
                                                            ' Montant : ' + money.format(amount),
                                                            ' Part : ' + percent + '%',
                                                            ' Ventes : ' + (entry?.count ?? 0),
                                                        ];
                                                    },
                                                },
                                            },
                                        },
                                    },
                                });
                            }
                        });
                    </script>
                @endpush
            @endif

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="dashboard-stat-card border-t-4 border-t-primary">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-dark">Ventes (7 jours)</p>
                    <p class="mt-2 text-3xl font-semibold text-primary">{{ $weekSalesCount }}</p>
                    <p class="mt-1 text-sm text-neutral-600">Sur votre périmètre</p>
                </div>
                <div class="dashboard-stat-card dashboard-stat-card--soft border-t-4 border-t-primary-light">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-dark">Ventes aujourd’hui</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($todaySalesTotal) }}</p>
                    <p class="mt-1 text-xs text-neutral-600">
                        {{ $todaySalesCount }} vente{{ $todaySalesCount > 1 ? 's' : '' }}
                        · Cash {{ \App\Support\Money::usd($todayCashTotal) }}
                        · Crédit {{ \App\Support\Money::usd($todayCreditTotal) }}
                    </p>
                </div>
                <div class="dashboard-stat-card @if ($lowStocksCount > 0) border-t-4 border-t-red-500 bg-red-50/90 @else border-t-4 border-t-primary @endif">
                    <p class="text-xs font-semibold uppercase tracking-wide @if ($lowStocksCount > 0) text-red-800 @else text-primary-dark @endif">Alertes stock</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums @if ($lowStocksCount > 0) text-red-700 @else text-primary @endif">{{ $lowStocksCount }}</p>
                    <p class="mt-1 text-sm @if ($lowStocksCount > 0) font-medium text-red-900 @else text-neutral-600 @endif">
                        @if ($lowStocksCount > 0)
                            Sous le seuil - action requise
                        @else
                            Aucune alerte @if (! $seesAllBranches) (votre branche) @endif
                        @endif
                    </p>
                    <a href="{{ route('stocks.index') }}" class="mt-2 inline-block text-sm font-medium @if ($lowStocksCount > 0) text-red-800 underline decoration-red-300 hover:text-red-950 @else text-primary hover:underline @endif">Stocks →</a>
                </div>
                @if ($canAccessAccounting)
                    <div class="dashboard-stat-card dashboard-stat-card--dark">
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/75">Caisse comptable (cumul)</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-white">{{ \App\Support\Money::usd($accountingCaisse) }}</p>
                        <p class="mt-1 text-sm text-white/80">Débit − crédit (toutes écritures)</p>
                        <a href="{{ route('accounting.index') }}" class="mt-2 inline-block text-sm font-medium text-white/90 hover:text-white hover:underline">Comptabilité →</a>
                    </div>
                @else
                    <div class="dashboard-stat-card dashboard-stat-card--accent border-t-4 border-t-primary-dark">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-dark">Produits (périmètre)</p>
                        <p class="mt-2 text-3xl font-semibold text-primary-dark">{{ $productsCount }}</p>
                        <p class="mt-1 text-sm text-neutral-600">{{ $seesAllBranches ? 'Vue agrégée (toutes branches)' : 'Liés à votre branche' }}</p>
                        <a href="{{ route('products.index') }}" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Produits →</a>
                    </div>
                @endif
            </div>

            <section
                class="app-panel overflow-hidden border-primary/15"
                x-data="dashboardStockByDepartment({
                    departments: @js($stockByDepartment),
                    totalProducts: {{ (int) $productsInStockCount }},
                    previewLimit: 5,
                })"
                x-init="init()"
            >
                <div class="dashboard-panel-header">
                    <div>
                        <h2 class="font-semibold text-primary-dark">Stocks par produit</h2>
                        <p class="mt-0.5 text-xs text-neutral-600">
                            Par département — 5 premiers produits par onglet
                            @if (! $seesAllBranches)
                                (votre branche)
                            @endif
                            , stock &gt; 0 uniquement.
                        </p>
                    </div>
                    <a href="{{ route('stocks.quantities-by-department') }}" class="text-sm font-medium text-primary hover:text-primary-dark">Voir tout</a>
                </div>

                @if ($productsInStockCount === 0)
                    <div class="px-5 py-10 text-center text-sm text-neutral-500">
                        Aucun produit en stock sur votre périmètre.
                    </div>
                @else
                    <div class="border-b border-neutral-100 bg-neutral-50/60 px-3 pt-2">
                        <div class="flex gap-1 overflow-x-auto pb-0.5" role="tablist" aria-label="Départements">
                            <template x-for="dept in departments" :key="dept.id">
                                <button
                                    type="button"
                                    role="tab"
                                    :id="'stock-dept-tab-' + dept.id"
                                    :aria-selected="activeDepartmentId === dept.id"
                                    :aria-controls="'stock-dept-panel-' + dept.id"
                                    @click="selectDepartment(dept.id)"
                                    class="shrink-0 rounded-t-lg border border-b-0 px-3 py-2 text-xs font-semibold transition sm:px-4 sm:text-sm"
                                    :class="activeDepartmentId === dept.id
                                        ? 'border-primary/30 bg-white text-primary shadow-sm'
                                        : 'border-transparent bg-transparent text-neutral-600 hover:bg-white/70 hover:text-primary-dark'"
                                >
                                    <span x-text="dept.name"></span>
                                    <span
                                        class="ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] tabular-nums"
                                        :class="activeDepartmentId === dept.id ? 'bg-primary/10 text-primary' : 'bg-neutral-200/80 text-neutral-600'"
                                        x-text="dept.product_count"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <template x-for="dept in departments" :key="'panel-' + dept.id">
                        <div
                            x-show="activeDepartmentId === dept.id"
                            x-cloak
                            role="tabpanel"
                            :id="'stock-dept-panel-' + dept.id"
                            :aria-labelledby="'stock-dept-tab-' + dept.id"
                        >
                            <ul class="divide-y divide-neutral-100">
                                <template x-for="product in visibleProducts(dept)" :key="product.product_id">
                                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                                        <div class="min-w-0">
                                            <p class="font-medium text-neutral-900" x-text="product.product_name"></p>
                                            <p class="text-xs text-neutral-500" x-text="product.product_sku || '—'"></p>
                                        </div>
                                        <p class="shrink-0 tabular-nums font-semibold text-primary-dark" x-text="product.total_quantity"></p>
                                    </li>
                                </template>
                            </ul>
                            <div class="flex flex-col gap-3 border-t border-neutral-100 bg-neutral-50/80 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-neutral-600">
                                    <span x-text="dept.product_count"></span> produit<span x-show="dept.product_count > 1">s</span>
                                    dans <span x-text="dept.name"></span>
                                    · <span x-text="dept.total_quantity"></span> unités
                                    <span x-show="dept.product_count > previewLimit">
                                        · <span x-text="previewLimit"></span> affichés
                                    </span>
                                </p>
                                <a
                                    x-show="dept.product_count > previewLimit || totalProducts > previewLimit"
                                    href="{{ route('stocks.quantities-by-department') }}"
                                    class="app-btn-secondary !px-3 !py-1.5 !text-xs"
                                >
                                    Voir tout par département
                                </a>
                            </div>
                        </div>
                    </template>
                @endif
            </section>

            <section class="app-panel overflow-hidden border-primary/15">
                <div class="dashboard-panel-header">
                    <div>
                        <h2 class="font-semibold text-primary-dark">Ventes crédit - échéances</h2>
                        <p class="mt-0.5 text-xs text-neutral-600">
                            Échéance atteinte ou dépassée, et échéances dans les {{ $creditDueSoonDays }} prochains jours (solde restant).
                        </p>
                    </div>
                    <a href="{{ route('reports.credit-sales') }}" class="text-sm font-medium text-primary hover:text-primary-dark">Rapport crédit</a>
                </div>

                @if ($creditDueReached->isNotEmpty())
                    <div class="border-b border-red-100 bg-red-50/60 px-5 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-900">Échéance atteinte ou dépassée</p>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($creditDueReached as $sale)
                            <li class="flex flex-col gap-3 px-5 py-3 transition-colors hover:bg-red-50/40 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate font-mono font-medium text-neutral-900">{{ $sale->reference }}</p>
                                    <p class="text-sm text-neutral-700">{{ $sale->displayClientName() ?? 'Client non renseigné' }}</p>
                                    <p class="text-xs text-neutral-500">
                                        Échéance :
                                        <span class="font-medium text-red-800">{{ $sale->credit_due_date->translatedFormat('d/m/Y') }}</span>
                                        @if ($seesAllBranches && $sale->branch)
                                            · {{ $sale->branch->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3 sm:text-right">
                                    <div>
                                        <p class="text-xs text-neutral-500">Solde</p>
                                        <p class="tabular-nums font-semibold text-red-900">{{ \App\Support\Money::usd($sale->remainingAmountValue()) }}</p>
                                    </div>
                                    @if ($sale->branch)
                                        <a href="{{ route('sales.show', [$sale->branch, $sale]) }}" class="app-btn-primary !px-3 !py-1.5 !text-xs">Ouvrir</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($creditDueSoon->isNotEmpty())
                    <div class="border-b border-amber-100 bg-amber-50/60 px-5 py-2 {{ $creditDueReached->isNotEmpty() ? 'border-t' : '' }}">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-950">Échéance proche</p>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($creditDueSoon as $sale)
                            <li class="flex flex-col gap-3 px-5 py-3 transition-colors hover:bg-amber-50/40 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate font-mono font-medium text-neutral-900">{{ $sale->reference }}</p>
                                    <p class="text-sm text-neutral-700">{{ $sale->displayClientName() ?? 'Client non renseigné' }}</p>
                                    <p class="text-xs text-neutral-500">
                                        Échéance :
                                        <span class="font-medium text-amber-900">{{ $sale->credit_due_date->translatedFormat('d/m/Y') }}</span>
                                        @if ($seesAllBranches && $sale->branch)
                                            · {{ $sale->branch->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3 sm:text-right">
                                    <div>
                                        <p class="text-xs text-neutral-500">Solde</p>
                                        <p class="tabular-nums font-semibold text-amber-950">{{ \App\Support\Money::usd($sale->remainingAmountValue()) }}</p>
                                    </div>
                                    @if ($sale->branch)
                                        <a href="{{ route('sales.show', [$sale->branch, $sale]) }}" class="app-btn-secondary !px-3 !py-1.5 !text-xs">Ouvrir</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($creditDueReached->isEmpty() && $creditDueSoon->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-neutral-500">
                        Aucune vente crédit avec échéance proche ou atteinte.
                    </div>
                @endif
            </section>

            <div class="grid gap-8 lg:grid-cols-2">
                <section class="app-panel overflow-hidden border-primary/15">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2 class="font-semibold text-primary-dark">Dernières ventes</h2>
                            <p class="mt-0.5 text-xs text-neutral-600">Les 5 enregistrements les plus récents sur votre périmètre</p>
                        </div>
                        <a href="{{ route('sales.overview') }}" class="text-sm font-medium text-primary hover:text-primary-dark">Tout voir</a>
                    </div>
                    <ul class="divide-y divide-primary/5">
                        @forelse ($recentSales as $sale)
                            <li class="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-primary-soft/40">
                                <div class="min-w-0">
                                    <p class="truncate font-mono font-medium text-neutral-900">{{ $sale->reference }}</p>
                                    @if ($seesAllBranches)
                                        <p class="text-xs text-neutral-500">{{ $sale->branch?->name ?? 'Branche introuvable' }}</p>
                                    @endif
                                    <p class="text-xs text-neutral-500">{{ $sale->sold_at?->translatedFormat('d M Y, H:i') ?? '-' }}</p>
                                    @if ($sale->user)
                                        <p class="text-xs text-neutral-500">Par {{ $sale->user->name }}</p>
                                    @endif
                                </div>
                                @if ($sale->branch)
                                    <a href="{{ route('sales.show', [$sale->branch, $sale]) }}" class="app-btn-primary shrink-0 !px-3 !py-1.5 !text-xs">Ouvrir</a>
                                @else
                                    <span class="shrink-0 text-xs text-neutral-400">-</span>
                                @endif
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-neutral-500">Aucune vente récente.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="app-panel overflow-hidden @if ($lowStocksCount > 0) !border-red-200/80 @else border-primary/15 @endif">
                    <div class="dashboard-panel-header @if ($lowStocksCount > 0) !border-red-100 !from-red-50/80 !to-white @endif">
                        <div>
                            <h2 class="font-semibold @if ($lowStocksCount > 0) text-red-900 @else text-primary-dark @endif">Stocks bas</h2>
                            @if ($lowStocksCount > 0)
                                <p class="mt-0.5 text-xs font-medium text-red-800">5 premières lignes sous le seuil (emplacement ou produit) - voir la matrice pour la liste complète</p>
                            @else
                                <p class="mt-0.5 text-xs text-neutral-600">Aucune alerte sur votre périmètre</p>
                            @endif
                        </div>
                        <a href="{{ route('stocks.index') }}" class="text-sm @if ($lowStocksCount > 0) font-medium text-red-800 hover:text-red-950 @else font-medium text-primary hover:text-primary-dark @endif">Stocks</a>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @forelse ($lowStocks as $stock)
                            @php
                                $product = $stock->product;
                                $location = $stock->location;
                                $seuil = $stock->minimum_stock ?? $product?->minimum_stock;
                            @endphp
                            <li class="border-l-4 border-red-500 bg-red-50/40 px-5 py-3">
                                <p class="font-medium text-neutral-900">{{ $product?->name ?? 'Produit introuvable' }}</p>
                                <p class="text-sm text-neutral-600">
                                    {{ $location?->name ?? 'Emplacement introuvable' }}
                                    @if ($seesAllBranches)
                                        <span class="text-neutral-400">({{ $location?->branch?->name ?? 'Branche introuvable' }})</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-red-900/90">
                                    Qté actuelle : <span class="font-semibold tabular-nums">{{ $stock->quantity }}</span>
                                    - Seuil : <span class="font-semibold tabular-nums">{{ $seuil ?? '-' }}</span>
                                    @if ($stock->minimum_stock !== null && $product?->minimum_stock !== null && (int) $stock->minimum_stock !== (int) $product->minimum_stock)
                                        <span class="text-neutral-500">(empl.)</span>
                                    @elseif ($stock->minimum_stock === null && $product?->minimum_stock !== null)
                                        <span class="text-neutral-500">(seuil produit)</span>
                                    @endif
                                </p>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-neutral-500">Aucune alerte - tous les stocks suivis sont au-dessus du minimum.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </x-caisse-flow>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardStockByDepartment', (config) => ({
                departments: config.departments || [],
                totalProducts: config.totalProducts || 0,
                previewLimit: config.previewLimit || 5,
                activeDepartmentId: null,

                init() {
                    if (this.departments.length > 0) {
                        this.activeDepartmentId = this.departments[0].id;
                    }
                },

                selectDepartment(id) {
                    this.activeDepartmentId = id;
                },

                visibleProducts(dept) {
                    return (dept?.products || []).slice(0, this.previewLimit);
                },
            }));
        });
    </script>
    @endpush
</x-app-layout>
