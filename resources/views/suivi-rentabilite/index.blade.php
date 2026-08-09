<x-app-layout>
    <x-slot name="header">Suivi de rentabilité</x-slot>

    <div
        x-data="suiviCoutPage({
            costCenters: @js($costCenters),
            transactionTypes: @js($transactionTypes),
            storeEntryUrl: @js(route('suivi-rentabilite.entries.store')),
            updateEntryUrl: @js(url('/suivi-rentabilite/entries')),
            storeCenterUrl: @js(route('suivi-rentabilite.centres.store')),
            storeTypeUrl: @js(route('suivi-rentabilite.types.store')),
            listFilters: @js($filters),
            csrf: @js(csrf_token()),
        })"
        @keydown.escape.window="closeAll()"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="app-page-eyebrow">Suivi de rentabilité</p>
                        <h1 class="app-page-title">Suivi de rentabilité</h1>
                        <p class="app-page-desc max-w-2xl">
                            Suivi des mouvements financiers et analyse de rentabilité par centre et type de transaction.
                        </p>
                        <nav class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('suivi-rentabilite.centres.index') }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                Centres de coût
                            </a>
                            <a href="{{ route('suivi-rentabilite.types.index') }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                Types de transaction
                            </a>
                            <a href="{{ route('suivi-rentabilite.centres-report') }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                Rapport par centre
                            </a>
                        </nav>
                    </div>
                    <div class="flex shrink-0 items-start">
                        <button type="button" class="app-btn-primary" @click="openEntryModal()">
                            Nouvelle écriture
                        </button>
                    </div>
                </div>
            </x-slot>

            <div
                x-show="flashMessage"
                x-cloak
                x-transition
                class="mb-6 rounded-xl border px-4 py-3 text-sm"
                :class="flashType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900'"
                x-text="flashMessage"
            ></div>

            <form method="GET" action="{{ route('suivi-rentabilite') }}" class="app-filter-bar mb-6 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="xl:col-span-2">
                    <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Recherche</label>
                    <input
                        id="q"
                        name="q"
                        type="search"
                        value="{{ $filters['q'] }}"
                        placeholder="Centre de coût, type ou description…"
                        class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    />
                </div>
                <div>
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div>
                    <label for="direction" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Sens</label>
                    <select id="direction" name="direction" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Tous</option>
                        <option value="entry" @selected($filters['direction'] === 'entry')>Entrée</option>
                        <option value="exit" @selected($filters['direction'] === 'exit')>Sortie</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 md:col-span-2 xl:col-span-5">
                    <button type="submit" class="app-btn-primary">Filtrer</button>
                    <a href="{{ route('suivi-rentabilite') }}" class="app-btn-secondary">Réinitialiser</a>
                </div>
            </form>

            <div class="app-table-shell">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                <th class="px-4 py-3">Centre de coût</th>
                                <th class="px-4 py-3">Type de transaction</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right">Entrée</th>
                                <th class="px-4 py-3 text-right">Sortie</th>
                                <th class="px-4 py-3 text-right">Solde</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cost-tracking-body" class="divide-y divide-neutral-100 bg-white" @click="handleTableClick($event)">
                            @include('suivi-rentabilite.partials.entries-body', ['entries' => $entries])
                        </tbody>
                    </table>
                </div>
            </div>
        </x-caisse-flow>

        {{-- Modal nouvelle écriture / modification --}}
        <div
            x-show="entryOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="entry-modal-title"
        >
            <div class="absolute inset-0 bg-neutral-900/45" @click="closeEntryModal()"></div>
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-neutral-200 bg-white shadow-xl" @click.stop>
                <div class="border-b border-neutral-100 px-6 py-4">
                    <h2 id="entry-modal-title" class="text-lg font-semibold text-neutral-900" x-text="editingEntryId ? 'Modifier l\'écriture' : 'Nouvelle écriture'"></h2>
                </div>
                <form @submit.prevent="submitEntry()" class="space-y-4 px-6 py-5">
                    <div>
                        <label for="entry_date" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Date</label>
                        <input id="entry_date" type="date" x-model="form.occurred_on" required class="mt-1 block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary" />
                    </div>
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Sens</span>
                        <div class="mt-2 flex gap-4">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" value="entry" x-model="form.direction" class="text-primary focus:ring-primary" />
                                Entrée
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" value="exit" x-model="form.direction" class="text-primary focus:ring-primary" />
                                Sortie
                            </label>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label for="entry_center" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Centre de coût</label>
                            <button type="button" class="text-xs font-medium text-primary hover:underline" @click="showNewCenter = !showNewCenter">Nouveau centre…</button>
                        </div>
                        <select id="entry_center" x-model="form.cost_center_id" required class="mt-1 block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary">
                            <option value="">Choisir…</option>
                            <template x-for="center in costCenters" :key="center.id">
                                <option :value="center.id" x-text="center.name"></option>
                            </template>
                        </select>
                        <div x-show="showNewCenter" x-cloak class="mt-2 flex gap-2">
                            <input type="text" x-model="newCenterName" placeholder="Nom du centre" class="block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary" />
                            <button type="button" class="app-btn-secondary shrink-0" @click="createCenter()" :disabled="creatingCenter">Ajouter</button>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label for="entry_type" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Type de transaction</label>
                            <button type="button" class="text-xs font-medium text-primary hover:underline" @click="showNewType = !showNewType">Nouveau type…</button>
                        </div>
                        <select id="entry_type" x-model="form.cost_transaction_type_id" required class="mt-1 block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary">
                            <option value="">Choisir…</option>
                            <template x-for="type in transactionTypes" :key="type.id">
                                <option :value="type.id" x-text="type.name"></option>
                            </template>
                        </select>
                        <div x-show="showNewType" x-cloak class="mt-2 flex gap-2">
                            <input type="text" x-model="newTypeName" placeholder="Nom du type" class="block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary" />
                            <button type="button" class="app-btn-secondary shrink-0" @click="createType()" :disabled="creatingType">Ajouter</button>
                        </div>
                    </div>
                    <div>
                        <label for="entry_amount" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Montant en USD</label>
                        <input id="entry_amount" type="number" min="0.01" step="0.01" x-model="form.amount" required class="mt-1 block w-full rounded-xl border-neutral-200 text-sm tabular-nums focus:border-primary focus:ring-primary" />
                    </div>
                    <div>
                        <label for="entry_description" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Description</label>
                        <textarea id="entry_description" rows="3" x-model="form.description" class="mt-1 block w-full rounded-xl border-neutral-200 text-sm focus:border-primary focus:ring-primary"></textarea>
                    </div>
                    <p x-show="formError" x-cloak class="text-sm text-red-700" x-text="formError"></p>
                    <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                        <button type="button" class="app-btn-secondary" @click="closeEntryModal()">Annuler</button>
                        <button type="submit" class="app-btn-primary" :disabled="savingEntry">
                            <span x-show="!savingEntry" x-text="editingEntryId ? 'Enregistrer les modifications' : 'Enregistrer'"></span>
                            <span x-show="savingEntry" x-cloak>Enregistrement…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('suiviCoutPage', (config) => ({
                costCenters: config.costCenters,
                transactionTypes: config.transactionTypes,
                editingEntryId: null,
                entryOpen: false,
                showNewCenter: false,
                showNewType: false,
                newCenterName: '',
                newTypeName: '',
                creatingCenter: false,
                creatingType: false,
                savingEntry: false,
                formError: '',
                flashMessage: '',
                flashType: 'success',
                form: {
                    occurred_on: new Date().toISOString().slice(0, 10),
                    direction: 'entry',
                    cost_center_id: '',
                    cost_transaction_type_id: '',
                    amount: '',
                    description: '',
                },

                showFlash(message, type = 'success') {
                    this.flashMessage = message;
                    this.flashType = type;
                    clearTimeout(this._flashTimer);
                    this._flashTimer = setTimeout(() => { this.flashMessage = ''; }, 4000);
                },

                openEntryModal() {
                    this.formError = '';
                    this.editingEntryId = null;
                    this.resetForm();
                    this.entryOpen = true;
                },

                openEditModal(row) {
                    this.formError = '';
                    this.editingEntryId = Number(row.dataset.entryId);
                    this.form = {
                        occurred_on: row.dataset.occurredOn,
                        direction: row.dataset.direction,
                        cost_center_id: row.dataset.costCenterId,
                        cost_transaction_type_id: row.dataset.transactionTypeId,
                        amount: row.dataset.amount,
                        description: row.dataset.description || '',
                    };
                    this.showNewCenter = false;
                    this.showNewType = false;
                    this.entryOpen = true;
                },

                resetForm() {
                    this.form = {
                        occurred_on: new Date().toISOString().slice(0, 10),
                        direction: 'entry',
                        cost_center_id: '',
                        cost_transaction_type_id: '',
                        amount: '',
                        description: '',
                    };
                },

                closeEntryModal() {
                    this.entryOpen = false;
                    this.editingEntryId = null;
                    this.showNewCenter = false;
                    this.showNewType = false;
                },

                handleTableClick(event) {
                    const button = event.target.closest('[data-action]');
                    if (!button) return;

                    const row = button.closest('tr[data-entry-id]');
                    if (!row) return;

                    const action = button.dataset.action;
                    if (action === 'edit') {
                        this.openEditModal(row);
                    } else if (action === 'delete') {
                        this.deleteEntry(row);
                    }
                },

                applyListResponse(data) {
                    if (data.rows_html) {
                        document.getElementById('cost-tracking-body').innerHTML = data.rows_html;
                    }
                    this.showFlash(data.message);
                },

                ajaxListFilters() {
                    return {
                        filter_q: config.listFilters.q || null,
                        filter_date_from: config.listFilters.date_from || null,
                        filter_date_to: config.listFilters.date_to || null,
                        filter_direction: config.listFilters.direction || null,
                    };
                },

                closeAll() {
                    if (this.entryOpen) {
                        this.closeEntryModal();
                    }
                },

                async postJson(url, payload, method = 'POST') {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = data.message;
                        if (!msg && data.errors) {
                            msg = Object.values(data.errors).flat().join(' ');
                        }
                        throw new Error(msg || 'Erreur.');
                    }

                    return data;
                },

                async createCenter() {
                    if (!this.newCenterName.trim()) return;
                    this.creatingCenter = true;
                    try {
                        const data = await this.postJson(config.storeCenterUrl, { name: this.newCenterName.trim() });
                        this.costCenters.push({ id: data.id, name: data.name });
                        this.costCenters.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
                        this.form.cost_center_id = String(data.id);
                        this.newCenterName = '';
                        this.showNewCenter = false;
                    } catch (err) {
                        this.formError = err.message;
                    } finally {
                        this.creatingCenter = false;
                    }
                },

                async createType() {
                    if (!this.newTypeName.trim()) return;
                    this.creatingType = true;
                    try {
                        const data = await this.postJson(config.storeTypeUrl, { name: this.newTypeName.trim() });
                        this.transactionTypes.push({ id: data.id, name: data.name });
                        this.transactionTypes.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
                        this.form.cost_transaction_type_id = String(data.id);
                        this.newTypeName = '';
                        this.showNewType = false;
                    } catch (err) {
                        this.formError = err.message;
                    } finally {
                        this.creatingType = false;
                    }
                },

                async submitEntry() {
                    this.formError = '';
                    this.savingEntry = true;
                    try {
                        const payload = {
                            occurred_on: this.form.occurred_on,
                            direction: this.form.direction,
                            cost_center_id: this.form.cost_center_id,
                            cost_transaction_type_id: this.form.cost_transaction_type_id,
                            amount: this.form.amount,
                            description: this.form.description,
                            ...this.ajaxListFilters(),
                        };

                        const url = this.editingEntryId
                            ? `${config.updateEntryUrl}/${this.editingEntryId}`
                            : config.storeEntryUrl;
                        const method = this.editingEntryId ? 'PUT' : 'POST';

                        const data = await this.postJson(url, payload, method);

                        this.applyListResponse(data);
                        const wasCreate = !this.editingEntryId;
                        this.closeEntryModal();
                        if (wasCreate) {
                            this.resetForm();
                        }
                    } catch (err) {
                        this.formError = err.message;
                    } finally {
                        this.savingEntry = false;
                    }
                },

                async deleteEntry(row) {
                    const entryId = row.dataset.entryId;
                    const description = row.dataset.description || 'cette écriture';
                    const label = description.length > 60 ? description.slice(0, 60) + '…' : description;

                    if (!confirm(`Supprimer définitivement ${label ? '« ' + label + ' »' : 'cette écriture'} ?`)) {
                        return;
                    }

                    try {
                        const data = await this.postJson(
                            `${config.updateEntryUrl}/${entryId}`,
                            this.ajaxListFilters(),
                            'DELETE',
                        );
                        this.applyListResponse(data);
                    } catch (err) {
                        this.showFlash(err.message, 'error');
                    }
                },
            }));
        });
    </script>
    @endpush
</x-app-layout>
