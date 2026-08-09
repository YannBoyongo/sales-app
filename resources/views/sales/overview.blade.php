<x-app-layout>
    <x-slot name="header">Toutes les ventes</x-slot>

    <div
        x-data="salesOverviewInfinite({
            nextPageUrl: @js($infiniteNextPageUrl),
            total: {{ $sales->total() }},
            loadedTo: {{ $sales->lastItem() ?? 0 }},
        })"
    >
        <x-page-header title="Toutes les ventes" :action="auth()->user()?->canAccessPosSales() ? 'Nouvelle vente' : null" :action-href="auth()->user()?->canAccessPosSales() ? route('sales.entry') : null" />

        @if ($errors->has('sale'))
            <div class="app-alert-danger" role="alert">{{ $errors->first('sale') }}</div>
        @endif

        @if ($canApproveDiscounts && $pendingDiscountCount > 0)
            <div class="app-alert-warning flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p>
                    <span class="font-semibold">{{ $pendingDiscountCount }} remise{{ $pendingDiscountCount > 1 ? 's' : '' }}</span>
                    en attente d’approbation administrateur.
                </p>
                <div class="flex flex-wrap gap-2">
                    @if (request()->boolean('remise'))
                        <a href="{{ route('sales.overview', request()->only(['date_from', 'date_to', 'branch_id', 'pos_terminal_id', 'payment_type'])) }}" class="inline-flex items-center rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100">Toutes les ventes</a>
                    @else
                        <a href="{{ route('sales.overview', array_merge(request()->only(['date_from', 'date_to', 'branch_id', 'pos_terminal_id', 'payment_type']), ['remise' => 1])) }}" class="inline-flex items-center rounded-md border border-primary/40 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/15">Voir les remises en attente</a>
                    @endif
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('sales.overview') }}" class="app-filter-bar mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
            @if (request()->boolean('remise'))
                <input type="hidden" name="remise" value="1" />
            @endif
            <div>
                <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
                <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            @if ($showsMultipleBranches)
                <div>
                    <label for="branch_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Branche</label>
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Toutes</option>
                        @foreach ($branchesForFilter as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="pos_terminal_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Point de vente</label>
                <select id="pos_terminal_id" name="pos_terminal_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Tous</option>
                    @foreach ($posTerminals as $terminal)
                        <option value="{{ $terminal->id }}" @selected((string) ($filters['pos_terminal_id'] ?? '') === (string) $terminal->id)>
                            @if ($showsMultipleTerminalBranches)
                                {{ $terminal->branch->name }} - {{ $terminal->name }}
                            @else
                                {{ $terminal->name }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="payment_type" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Paiement</label>
                <select id="payment_type" name="payment_type" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Tous</option>
                    <option value="cash" @selected(($filters['payment_type'] ?? '') === 'cash')>Cash</option>
                    <option value="credit" @selected(($filters['payment_type'] ?? '') === 'credit')>Crédit</option>
                    <option value="caution" @selected(($filters['payment_type'] ?? '') === 'caution')>Caution</option>
                </select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit" class="app-btn-primary">Filtrer</button>
                <a href="{{ route('sales.overview', request()->boolean('remise') ? ['remise' => 1] : []) }}" class="app-btn-secondary">Réinitialiser</a>
            </div>
        </form>
        @if ($errors->has('date_from') || $errors->has('date_to'))
            <div class="app-alert-danger" role="alert">
                {{ $errors->first('date_from') ?: $errors->first('date_to') }}
            </div>
        @endif

        @if (! empty($branchTotals))
            <div class="mb-6 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-neutral-900">Totaux par branche</h2>
                    <p class="text-xs text-neutral-500">Répartition des ventes filtrées (hors pagination).</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Branche</th>
                                <th class="px-4 py-3 text-right">Ventes</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Payé</th>
                                <th class="px-4 py-3 text-right">Solde</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($branchTotals as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-neutral-900">{{ $row['branch_name'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['count'], 0, ',', ' ') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($row['expected']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($row['paid']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($row['remaining']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="app-table-scroll-panel" x-ref="tableScroll" @scroll="onTableScroll()">
            <table class="app-table-sticky-first-col min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 whitespace-nowrap">Réf.</th>
                        <th class="px-4 py-3 whitespace-nowrap">Terminal POS</th>
                        <th class="px-4 py-3 whitespace-nowrap">Client</th>
                        <th class="px-4 py-3 whitespace-nowrap">Paiement</th>
                        <th class="px-4 py-3 min-w-[12rem]">Articles</th>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Payé</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Solde</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody id="sales-overview-tbody" class="divide-y divide-neutral-100" x-ref="tbody">
                    @include('sales.partials.overview-rows')
                </tbody>
                @if (($summaryTotals['count'] ?? 0) > 0)
                    <tfoot class="border-t-2 border-neutral-200 bg-neutral-50/90 text-sm font-semibold text-neutral-900">
                        <tr>
                            <td colspan="6" class="px-4 py-3">Total ({{ number_format($summaryTotals['count'], 0, ',', ' ') }} vente{{ $summaryTotals['count'] > 1 ? 's' : '' }})</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($summaryTotals['expected']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($summaryTotals['paid']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($summaryTotals['remaining']) }}</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            <div x-ref="sentinel" class="h-8 w-full" aria-hidden="true"></div>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-neutral-600" x-text="statusLabel()"></p>
            <p class="text-sm text-neutral-500" x-show="loading" x-cloak>Chargement…</p>
            <p class="text-sm text-neutral-500" x-show="!loading && !nextPageUrl && total > 0" x-cloak>Fin de la liste</p>
        </div>

        <button
            type="button"
            x-show="showBackToTop"
            x-cloak
            x-transition.opacity
            @click="scrollToTop()"
            class="fixed bottom-6 right-6 z-40 inline-flex h-11 w-11 items-center justify-center rounded-full bg-primary text-white shadow-lg ring-1 ring-black/5 hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            title="Retour en haut"
            aria-label="Retour en haut"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            </svg>
        </button>
    </div>

    @push('scripts')
        <script>
            window.salesOverviewInfinite = function ({ nextPageUrl, total, loadedTo }) {
                return {
                    nextPageUrl,
                    total,
                    loadedTo,
                    loading: false,
                    showBackToTop: false,
                    observer: null,

                    init() {
                        this.onTableScroll();
                        this.$nextTick(() => this.setupObserver());
                    },

                    statusLabel() {
                        if (this.total <= 0) {
                            return '0 vente';
                        }
                        return `Affichage de 1 à ${this.loadedTo} sur ${this.total} vente${this.total > 1 ? 's' : ''}`;
                    },

                    onTableScroll() {
                        const scrollEl = this.$refs.tableScroll;
                        this.showBackToTop = scrollEl ? scrollEl.scrollTop > 400 : false;
                    },

                    scrollToTop() {
                        this.$refs.tableScroll?.scrollTo({ top: 0, behavior: 'smooth' });
                    },

                    setupObserver() {
                        if (! this.$refs.sentinel || ! this.$refs.tableScroll || ! ('IntersectionObserver' in window)) {
                            return;
                        }

                        this.observer = new IntersectionObserver((entries) => {
                            if (entries.some((entry) => entry.isIntersecting)) {
                                this.loadMore();
                            }
                        }, { root: this.$refs.tableScroll, rootMargin: '120px 0px' });

                        this.observer.observe(this.$refs.sentinel);
                    },

                    async loadMore() {
                        if (this.loading || ! this.nextPageUrl) {
                            return;
                        }

                        this.loading = true;
                        try {
                            const response = await fetch(this.nextPageUrl, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (! response.ok) {
                                throw new Error('Impossible de charger plus de ventes.');
                            }

                            const data = await response.json();
                            if (data.html && this.$refs.tbody) {
                                this.$refs.tbody.insertAdjacentHTML('beforeend', data.html);
                            }
                            this.nextPageUrl = data.next_page_url || null;
                            this.loadedTo = data.to || this.loadedTo;
                            this.total = data.total ?? this.total;
                        } catch (error) {
                            console.error(error);
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            };
        </script>
    @endpush
</x-app-layout>
