<x-app-layout>
    <x-slot name="header">Transferts de stock</x-slot>

    <div
        x-data="stockTransfersInfinite({
            nextPageUrl: @js($infiniteNextPageUrl),
            total: {{ $transfers->total() }},
            loadedTo: {{ $transfers->lastItem() ?? 0 }},
        })"
        @scroll.window="onScroll()"
    >
        <x-page-header
            title="Transferts de stock"
            :action="auth()->user()?->isInventoryReadOnly() ? null : 'Nouveau transfert'"
            :action-href="auth()->user()?->isInventoryReadOnly() ? null : route('stock-transfers.create')"
        />

        @if (session('success'))
            <div class="mb-4 rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ session('success') }}</div>
        @endif

        <form
            method="GET"
            action="{{ route('stock-transfers.index') }}"
            class="app-filter-bar sticky top-16 z-20 mb-6 grid gap-3 border border-slate-200/80 bg-white/95 shadow-md backdrop-blur-md sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8"
            x-data="stockTransferLocationFilters({
                optionsByScope: @js($locationFilterOptions),
                initialScope: @js($filters['transfer_scope'] ?? ''),
                initialFromId: @js(isset($filters['from_location_id']) ? (string) $filters['from_location_id'] : ''),
                initialToId: @js(isset($filters['to_location_id']) ? (string) $filters['to_location_id'] : ''),
            })"
        >
            <div>
                <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
                <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Statut</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Tous</option>
                    <option value="{{ \App\Models\StockTransfer::STATUS_PENDING }}" @selected(($filters['status'] ?? '') === \App\Models\StockTransfer::STATUS_PENDING)>{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_PENDING) }}</option>
                    <option value="{{ \App\Models\StockTransfer::STATUS_CONFIRMED }}" @selected(($filters['status'] ?? '') === \App\Models\StockTransfer::STATUS_CONFIRMED)>{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_CONFIRMED) }}</option>
                    <option value="{{ \App\Models\StockTransfer::STATUS_CANCELLED }}" @selected(($filters['status'] ?? '') === \App\Models\StockTransfer::STATUS_CANCELLED)>{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_CANCELLED) }}</option>
                </select>
            </div>
            <div>
                <label for="transfer_scope" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</label>
                <select
                    id="transfer_scope"
                    name="transfer_scope"
                    class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    x-model="scope"
                    @change="onScopeChange()"
                >
                    <option value="">Tous</option>
                    <option value="{{ \App\Models\StockTransfer::SCOPE_INTERNAL }}">{{ \App\Models\StockTransfer::scopeLabel(\App\Models\StockTransfer::SCOPE_INTERNAL) }}</option>
                    <option value="{{ \App\Models\StockTransfer::SCOPE_EXTERNAL }}">{{ \App\Models\StockTransfer::scopeLabel(\App\Models\StockTransfer::SCOPE_EXTERNAL) }}</option>
                </select>
            </div>
            <div>
                <label for="from_location_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Origine</label>
                <select
                    id="from_location_id"
                    name="from_location_id"
                    class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    x-model="fromId"
                >
                    <option value="">Tous</option>
                    <template x-for="location in fromOptions" :key="'from-' + location.id">
                        <option :value="String(location.id)" x-text="locationLabel(location)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="to_location_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Destination</label>
                <select
                    id="to_location_id"
                    name="to_location_id"
                    class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    x-model="toId"
                >
                    <option value="">Tous</option>
                    <template x-for="location in toOptions" :key="'to-' + location.id">
                        <option :value="String(location.id)" x-text="locationLabel(location)"></option>
                    </template>
                </select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2 xl:col-span-2">
                <button type="submit" class="app-btn-primary">Filtrer</button>
                <a href="{{ route('stock-transfers.index') }}" class="app-btn-secondary">Réinitialiser</a>
            </div>
        </form>
        @if ($errors->has('date_from') || $errors->has('date_to'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('date_from') ?: $errors->first('date_to') }}
            </div>
        @endif

        <div class="app-table-shell">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3">Réf.</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">De</th>
                        <th class="px-4 py-3">Vers</th>
                        <th class="px-4 py-3 min-w-[12rem]">Produits</th>
                        <th class="px-4 py-3">Par</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="stock-transfers-tbody" class="divide-y divide-neutral-100" x-ref="tbody">
                    @include('stock_transfers.partials.index-rows')
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-neutral-600" x-text="statusLabel()"></p>
            <p class="text-sm text-neutral-500" x-show="loading" x-cloak>Chargement…</p>
            <p class="text-sm text-neutral-500" x-show="!loading && !nextPageUrl && total > 0" x-cloak>Fin de la liste</p>
        </div>

        <div x-ref="sentinel" class="h-8 w-full" aria-hidden="true"></div>

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
            window.stockTransferLocationFilters = function ({ optionsByScope, initialScope, initialFromId, initialToId }) {
                return {
                    optionsByScope,
                    scope: initialScope || '',
                    fromId: initialFromId || '',
                    toId: initialToId || '',

                    get fromOptions() {
                        return this.optionsFor('from');
                    },

                    get toOptions() {
                        return this.optionsFor('to');
                    },

                    optionsFor(side) {
                        const key = this.scope === 'internal' || this.scope === 'external'
                            ? this.scope
                            : 'all';
                        return this.optionsByScope?.[key]?.[side] || [];
                    },

                    locationLabel(location) {
                        if (! location) {
                            return '';
                        }
                        if (location.branch_name) {
                            return `${location.branch_name} - ${location.name}`;
                        }
                        return location.name;
                    },

                    onScopeChange() {
                        const fromIds = this.fromOptions.map((location) => String(location.id));
                        const toIds = this.toOptions.map((location) => String(location.id));
                        if (this.fromId && ! fromIds.includes(String(this.fromId))) {
                            this.fromId = '';
                        }
                        if (this.toId && ! toIds.includes(String(this.toId))) {
                            this.toId = '';
                        }
                    },
                };
            };

            window.stockTransfersInfinite = function ({ nextPageUrl, total, loadedTo }) {
                return {
                    nextPageUrl,
                    total,
                    loadedTo,
                    loading: false,
                    showBackToTop: false,
                    observer: null,

                    init() {
                        this.onScroll();
                        this.$nextTick(() => this.setupObserver());
                    },

                    statusLabel() {
                        if (this.total <= 0) {
                            return '0 transfert';
                        }
                        return `Affichage de 1 à ${this.loadedTo} sur ${this.total} transfert${this.total > 1 ? 's' : ''}`;
                    },

                    onScroll() {
                        this.showBackToTop = window.scrollY > 400;
                    },

                    scrollToTop() {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },

                    setupObserver() {
                        if (! this.$refs.sentinel || ! ('IntersectionObserver' in window)) {
                            return;
                        }

                        this.observer = new IntersectionObserver((entries) => {
                            if (entries.some((entry) => entry.isIntersecting)) {
                                this.loadMore();
                            }
                        }, { rootMargin: '240px 0px' });

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
                                throw new Error('Impossible de charger plus de transferts.');
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
