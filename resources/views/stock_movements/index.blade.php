<x-app-layout>
    <x-slot name="header">Mouvements de stock</x-slot>

    <div
        x-data="stockMovementsInfinite({
            nextPageUrl: @js($infiniteNextPageUrl),
            total: {{ $movements->total() }},
            loadedTo: {{ $movements->lastItem() ?? 0 }},
        })"
        x-init="$nextTick(() => setupObserver())"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="app-page-eyebrow">Stock</p>
                        <h1 class="app-page-title">Mouvements de stock</h1>
                        <p class="app-page-desc max-w-3xl">
                            Journal des entrées, sorties, transferts et ajustements sur votre périmètre. Les transferts liés à un bon de transfert affichent un lien lorsque vous y avez accès.
                        </p>
                    </div>
                    @if (auth()->user()?->canCreateStockMovement())
                        <a
                            href="{{ route('stock-movements.create') }}"
                            class="app-btn-primary shrink-0"
                        >
                            Nouveau mouvement
                        </a>
                    @endif
                </div>
            </x-slot>

            <div class="app-table-scroll-panel app-table-scroll-panel--20-rows" x-ref="tableScroll">
                <table class="app-table-sticky-first-col min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 whitespace-nowrap">Date</th>
                            <th class="px-4 py-3 whitespace-nowrap">Type</th>
                            <th class="px-4 py-3 whitespace-nowrap">Produit</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Qté</th>
                            <th class="px-4 py-3 min-w-[12rem]">Détail</th>
                            <th class="px-4 py-3 whitespace-nowrap">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody id="stock-movements-tbody" class="divide-y divide-neutral-100" x-ref="tbody">
                        @include('stock_movements.partials.index-rows', ['movements' => $movements])
                    </tbody>
                </table>
                <div x-ref="sentinel" class="h-8 w-full" aria-hidden="true"></div>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-neutral-600" x-text="statusLabel()"></p>
                <p class="text-sm text-neutral-500" x-show="loading" x-cloak>Chargement…</p>
                <p class="text-sm text-neutral-500" x-show="!loading && !nextPageUrl && total > 0" x-cloak>Fin de la liste</p>
            </div>
        </x-caisse-flow>
    </div>

    @push('scripts')
        <script>
            window.stockMovementsInfinite = function ({ nextPageUrl, total, loadedTo }) {
                return {
                    nextPageUrl,
                    total,
                    loadedTo,
                    loading: false,
                    observer: null,

                    statusLabel() {
                        if (this.total <= 0) {
                            return '0 mouvement';
                        }
                        return `Affichage de 1 à ${this.loadedTo} sur ${this.total} mouvement${this.total > 1 ? 's' : ''}`;
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
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (! response.ok) {
                                throw new Error('Impossible de charger plus de mouvements.');
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
