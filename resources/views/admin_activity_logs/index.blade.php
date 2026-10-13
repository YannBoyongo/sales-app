<x-app-layout>
    <x-slot name="header">Journal des administrateurs</x-slot>

    <div
        x-data="adminActivityLogsPage({
            nextPageUrl: @js($infiniteNextPageUrl),
            total: {{ $logs->total() }},
            loadedTo: {{ $logs->lastItem() ?? 0 }},
            destroyUrl: @js(route('pending-actions.destroy-many')),
        })"
        x-init="$nextTick(() => setupObserver())"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div>
                    <p class="app-page-eyebrow">Supervision</p>
                    <h1 class="app-page-title">Journal des administrateurs</h1>
                    <p class="app-page-desc max-w-3xl">
                        Historique des actions réalisées par les comptes <strong>Administrateur</strong> (y compris admins de branche). Visible uniquement par le super administrateur.
                    </p>
                </div>
            </x-slot>

            <section class="app-panel mb-6">
                <form method="GET" action="{{ route('pending-actions.index') }}" class="app-filter-bar grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div>
                        <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Du</label>
                        <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                    </div>
                    <div>
                        <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Au</label>
                        <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                    </div>
                    <div>
                        <label for="user_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Administrateur</label>
                        <select id="user_id" name="user_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                            <option value="">Tous</option>
                            @foreach ($adminUsers as $admin)
                                <option value="{{ $admin->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="branch_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Branche</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                            <option value="">Toutes</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="action" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</label>
                        <select id="action" name="action" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                            <option value="">Tous</option>
                            @foreach ($actionOptions as $actionCode)
                                <option value="{{ $actionCode }}" @selected(($filters['action'] ?? '') === $actionCode)>{{ $actionCode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="app-btn-primary">Filtrer</button>
                        <a href="{{ route('pending-actions.index') }}" class="app-btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </section>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span
                        x-show="selectedLogIds.length > 0"
                        x-cloak
                        class="inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
                        x-text="selectedLogIds.length + ' sélectionné' + (selectedLogIds.length > 1 ? 's' : '')"
                    ></span>
                </div>
                <button
                    type="button"
                    class="app-btn-secondary border-red-200 text-red-800 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="selectedLogIds.length === 0 || deleting"
                    @click="deleteSelected()"
                >
                    <span x-show="! deleting">Supprimer la sélection</span>
                    <span x-show="deleting" x-cloak>Suppression…</span>
                </button>
            </div>

            <p
                x-show="flashMessage"
                x-cloak
                class="mb-3 rounded-lg border px-3 py-2 text-sm"
                :class="flashType === 'danger' ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
                x-text="flashMessage"
                role="status"
            ></p>

            <div class="app-table-scroll-panel app-table-scroll-panel--20-rows" x-ref="tableScroll">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="w-12 px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-neutral-300 text-primary focus:ring-primary"
                                    aria-label="Tout sélectionner (lignes chargées)"
                                    :checked="allLoadedSelected()"
                                    @change="toggleAllLoaded($event.target.checked)"
                                />
                            </th>
                            <th class="px-4 py-3">Date / heure</th>
                            <th class="px-4 py-3">Administrateur</th>
                            <th class="px-4 py-3">Branche</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Description</th>
                        </tr>
                    </thead>
                    <tbody
                        id="admin-activity-logs-tbody"
                        class="divide-y divide-neutral-100 bg-white"
                        x-ref="tbody"
                        @change="onRowCheckboxChange($event)"
                    >
                        @include('admin_activity_logs.partials.index-rows', ['logs' => $logs])
                    </tbody>
                </table>
                <div x-ref="sentinel" class="h-8 w-full" aria-hidden="true"></div>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-neutral-600" x-text="statusLabel()"></p>
                <p class="text-sm text-neutral-500" x-show="loading" x-cloak>Chargement…</p>
                <p class="text-sm text-neutral-500" x-show="! loading && ! nextPageUrl && total > 0" x-cloak>Fin de la liste</p>
            </div>
        </x-caisse-flow>
    </div>

    @push('scripts')
        <script>
            window.adminActivityLogsPage = function ({ nextPageUrl, total, loadedTo, destroyUrl }) {
                return {
                    nextPageUrl,
                    total,
                    loadedTo,
                    destroyUrl,
                    loading: false,
                    deleting: false,
                    observer: null,
                    selectedLogIds: [],
                    flashMessage: null,
                    flashType: 'success',

                    statusLabel() {
                        if (this.total <= 0) {
                            return '0 entrée';
                        }
                        return `Affichage de 1 à ${this.loadedTo} sur ${this.total} entrée${this.total > 1 ? 's' : ''}`;
                    },

                    showFlash(message, type = 'success') {
                        this.flashMessage = message;
                        this.flashType = type;
                        clearTimeout(this._flashTimer);
                        this._flashTimer = setTimeout(() => { this.flashMessage = null; }, 5000);
                    },

                    logCheckboxes() {
                        return Array.from(this.$refs.tbody?.querySelectorAll('.admin-log-checkbox') ?? []);
                    },

                    isLogSelected(id) {
                        return this.selectedLogIds.includes(Number(id));
                    },

                    toggleLog(id, checked) {
                        const logId = Number(id);
                        if (checked && ! this.isLogSelected(logId)) {
                            this.selectedLogIds.push(logId);
                        } else if (! checked) {
                            this.selectedLogIds = this.selectedLogIds.filter((item) => item !== logId);
                        }
                    },

                    onRowCheckboxChange(event) {
                        const target = event.target;
                        if (! target.classList?.contains('admin-log-checkbox')) {
                            return;
                        }
                        this.toggleLog(target.value, target.checked);
                    },

                    allLoadedSelected() {
                        const checkboxes = this.logCheckboxes();
                        return checkboxes.length > 0 && checkboxes.every((checkbox) => this.isLogSelected(checkbox.value));
                    },

                    toggleAllLoaded(checked) {
                        this.logCheckboxes().forEach((checkbox) => {
                            checkbox.checked = checked;
                            this.toggleLog(checkbox.value, checked);
                        });
                    },

                    ensureEmptyState() {
                        const tbody = this.$refs.tbody;
                        if (! tbody) {
                            return;
                        }
                        if (! tbody.querySelector('tr[data-log-id]')) {
                            tbody.innerHTML = '<tr data-empty><td colspan="6" class="px-4 py-10 text-center text-neutral-500">Aucune action enregistrée pour le moment.</td></tr>';
                        }
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
                                throw new Error('Impossible de charger plus d’entrées.');
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
                            this.showFlash(error.message || 'Erreur de chargement.', 'danger');
                        } finally {
                            this.loading = false;
                        }
                    },

                    async deleteSelected() {
                        if (this.selectedLogIds.length === 0 || this.deleting) {
                            return;
                        }

                        const count = this.selectedLogIds.length;
                        const label = count > 1 ? 'ces ' + count + ' entrées' : 'cette entrée';
                        if (! confirm('Supprimer définitivement ' + label + ' du journal ?')) {
                            return;
                        }

                        this.deleting = true;
                        try {
                            const response = await fetch(this.destroyUrl, {
                                method: 'DELETE',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ log_ids: this.selectedLogIds }),
                            });

                            const data = await response.json().catch(() => ({}));
                            if (! response.ok) {
                                const firstValidationError = Object.values(data.errors ?? {}).flat()[0];
                                throw new Error(firstValidationError || data.message || 'Suppression impossible.');
                            }

                            const deletedIds = (data.deleted_ids ?? this.selectedLogIds).map((id) => Number(id));
                            deletedIds.forEach((id) => {
                                this.$refs.tbody?.querySelector(`tr[data-log-id="${id}"]`)?.remove();
                            });

                            const removed = data.deleted_count ?? deletedIds.length;
                            this.total = Math.max(0, this.total - removed);
                            this.loadedTo = Math.max(0, this.loadedTo - removed);
                            this.selectedLogIds = [];
                            this.ensureEmptyState();
                            this.showFlash(data.message || 'Entrées supprimées.');
                        } catch (error) {
                            this.showFlash(error.message || 'Suppression impossible.', 'danger');
                        } finally {
                            this.deleting = false;
                        }
                    },
                };
            };
        </script>
    @endpush
</x-app-layout>
