<x-app-layout>
    <x-slot name="header">Bons de caisse</x-slot>

        @php
        $voucherTableColspan = 7
            + (auth()->user()?->hasApplicationAdminAccess() ? 1 : 0)
            + (auth()->user()?->canAccessAccounting() ? 1 : 0);
        $canBulkAssignCashVouchers = auth()->user()?->hasApplicationAdminAccess() ?? false;
        $approvedVoucherTableColspan = $voucherTableColspan + ($canBulkAssignCashVouchers ? 1 : 0);
    @endphp

    <div
        x-data="{
            open: {{ ($errors->any() && ! old('edit_voucher_id')) ? 'true' : 'false' }},
            editOpen: {{ old('edit_voucher_id') ? 'true' : 'false' }},
            editVoucherId: @js(old('edit_voucher_id')),
            editVoucherNo: @js(old('voucher_no', '')),
            editAction: @js(old('edit_voucher_id') ? route('cash-vouchers.update', old('edit_voucher_id')) : ''),
            bulkOpen: false,
            bulkBranchId: '',
            bulkTerminalId: '',
            bulkSubmitting: false,
            bulkError: null,
            selectedVoucherIds: [],
            pendingCount: {{ $pendingVouchers->count() }},
            totalEntries: {{ $totalEntries }},
            totalExits: {{ $totalExits }},
            balance: {{ $balance }},
            filterBranchId: @js((string) ($filters['branch_id'] ?? '')),
            filterTerminalId: @js((string) ($filters['pos_terminal_id'] ?? '')),
            allTerminals: @js($allPosTerminalsForFilter),
            showsMultipleTerminalBranches: @js($showsMultipleTerminalBranchesAll),
            filteredTerminals() {
                if (! this.filterBranchId) {
                    return this.allTerminals;
                }

                return this.allTerminals.filter((terminal) => String(terminal.branch_id) === String(this.filterBranchId));
            },
            onBranchFilterChange() {
                const allowedIds = this.filteredTerminals().map((terminal) => String(terminal.id));
                if (this.filterTerminalId && ! allowedIds.includes(String(this.filterTerminalId))) {
                    this.filterTerminalId = '';
                }
            },
            onTerminalFilterChange() {
                if (! this.filterTerminalId) {
                    return;
                }

                const terminal = this.allTerminals.find((item) => String(item.id) === String(this.filterTerminalId));
                if (terminal?.branch_id && this.$refs.filterBranchSelect) {
                    this.filterBranchId = String(terminal.branch_id);
                }
            },
            terminalOptionLabel(terminal) {
                if (this.showsMultipleTerminalBranches && ! this.filterBranchId) {
                    return (terminal.branch_name ? terminal.branch_name + ' — ' : '') + terminal.name;
                }

                return terminal.name;
            },
            approvedCheckboxes() {
                return Array.from(this.$refs.approvedTbody?.querySelectorAll('.approved-voucher-checkbox:not(:disabled)') ?? []);
            },
            isVoucherSelected(id) {
                return this.selectedVoucherIds.includes(Number(id));
            },
            toggleVoucher(id, checked) {
                const voucherId = Number(id);
                if (checked && ! this.isVoucherSelected(voucherId)) {
                    this.selectedVoucherIds.push(voucherId);
                } else if (! checked) {
                    this.selectedVoucherIds = this.selectedVoucherIds.filter((item) => item !== voucherId);
                }
            },
            allLoadedApprovedSelected() {
                const checkboxes = this.approvedCheckboxes();
                return checkboxes.length > 0 && checkboxes.every((checkbox) => this.isVoucherSelected(checkbox.value));
            },
            toggleAllLoadedApproved(checked) {
                this.approvedCheckboxes().forEach((checkbox) => {
                    checkbox.checked = checked;
                    this.toggleVoucher(checkbox.value, checked);
                });
            },
            bulkFilteredTerminals() {
                if (! this.bulkBranchId) {
                    return [];
                }

                return this.allTerminals.filter((terminal) => String(terminal.branch_id) === String(this.bulkBranchId));
            },
            openBulkAssignment() {
                if (this.selectedVoucherIds.length === 0) {
                    return;
                }

                this.bulkError = null;
                this.bulkBranchId = this.filterBranchId || '';
                this.bulkTerminalId = '';
                this.bulkOpen = true;
            },
            onBulkBranchChange() {
                this.bulkTerminalId = '';
                this.bulkError = null;
            },
            async submitBulkAssignment() {
                if (! this.bulkBranchId || ! this.bulkTerminalId || this.selectedVoucherIds.length === 0) {
                    this.bulkError = 'Choisissez une branche et un terminal.';
                    return;
                }

                this.bulkSubmitting = true;
                this.bulkError = null;

                try {
                    const response = await fetch(@js(route('cash-vouchers.bulk-assignment')), {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            voucher_ids: this.selectedVoucherIds,
                            branch_id: this.bulkBranchId,
                            pos_terminal_id: this.bulkTerminalId,
                        }),
                    });

                    const data = await response.json().catch(() => ({}));
                    if (! response.ok) {
                        const firstValidationError = Object.values(data.errors ?? {}).flat()[0];
                        throw new Error(firstValidationError || data.message || 'Réaffectation impossible.');
                    }

                    window.location.reload();
                } catch (error) {
                    this.bulkError = error.message || 'Réaffectation impossible.';
                } finally {
                    this.bulkSubmitting = false;
                }
            },
            tableColspan: @js($voucherTableColspan),
            approvedNextPageUrl: @js($infiniteNextPageUrl),
            approvedTotal: {{ $approvedVouchers->total() }},
            approvedLoadedTo: {{ $approvedVouchers->lastItem() ?? 0 }},
            approvedLoading: false,
            approvedObserver: null,
            flashMessage: null,
            flashType: 'success',
            approvingId: null,
            moneyFormatter: new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            formatMoney(value) {
                return this.moneyFormatter.format(Number(value) || 0);
            },
            showFlash(message, type = 'success') {
                this.flashMessage = message;
                this.flashType = type;
                clearTimeout(this._flashTimer);
                this._flashTimer = setTimeout(() => { this.flashMessage = null; }, 4000);
            },
            ensurePendingEmptyState(tbody) {
                if (! tbody.querySelector('tr[data-voucher-id]')) {
                    tbody.innerHTML = `<tr data-empty><td colspan='${this.tableColspan}' class='px-4 py-8 text-center text-neutral-500'>Aucun bon en attente.</td></tr>`;
                }
            },
            ensureApprovedHasRow(tbody, rowHtml) {
                const emptyRow = tbody.querySelector('tr[data-empty]');
                if (emptyRow) {
                    emptyRow.remove();
                }
                tbody.insertAdjacentHTML('afterbegin', rowHtml);
            },
            async approveVoucher(url, event) {
                if (! confirm('Approuver ce bon de caisse ?')) {
                    return;
                }

                const row = event.currentTarget.closest('tr');
                const voucherId = row?.dataset?.voucherId;
                if (! row || ! voucherId) {
                    return;
                }

                this.approvingId = voucherId;
                event.currentTarget.disabled = true;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    const data = await res.json().catch(() => ({}));
                    if (! res.ok) {
                        throw new Error(data.message || 'Approbation impossible.');
                    }

                    row.remove();
                    this.pendingCount = Math.max(0, this.pendingCount - 1);
                    this.ensurePendingEmptyState(document.getElementById('pending-vouchers-body'));
                    this.ensureApprovedHasRow(document.getElementById('approved-vouchers-body'), data.row_html);
                    this.approvedTotal += 1;
                    this.approvedLoadedTo += 1;

                    if (data.voucher?.type === 'entry') {
                        this.totalEntries += Number(data.voucher.amount) || 0;
                    } else if (data.voucher?.type === 'exit') {
                        this.totalExits += Number(data.voucher.amount) || 0;
                    }
                    this.balance = this.totalEntries - this.totalExits;

                    this.showFlash(data.message || 'Bon de caisse approuvé.');
                } catch (err) {
                    event.currentTarget.disabled = false;
                    this.showFlash(err.message || 'Erreur lors de l’approbation.', 'danger');
                } finally {
                    this.approvingId = null;
                }
            },
            approvedStatusLabel() {
                if (this.approvedTotal <= 0) {
                    return '0 bon approuvé';
                }
                return `Affichage de 1 à ${this.approvedLoadedTo} sur ${this.approvedTotal} bon${this.approvedTotal > 1 ? 's' : ''} approuvé${this.approvedTotal > 1 ? 's' : ''}`;
            },
            setupApprovedObserver() {
                if (! this.$refs.approvedSentinel || ! this.$refs.approvedTableScroll || ! ('IntersectionObserver' in window)) {
                    return;
                }

                this.approvedObserver = new IntersectionObserver((entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        this.loadMoreApproved();
                    }
                }, { root: this.$refs.approvedTableScroll, rootMargin: '120px 0px' });

                this.approvedObserver.observe(this.$refs.approvedSentinel);
            },
            async loadMoreApproved() {
                if (this.approvedLoading || ! this.approvedNextPageUrl) {
                    return;
                }

                this.approvedLoading = true;
                try {
                    const response = await fetch(this.approvedNextPageUrl, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (! response.ok) {
                        throw new Error('Impossible de charger plus de bons approuvés.');
                    }

                    const data = await response.json();
                    if (data.html && this.$refs.approvedTbody) {
                        this.$refs.approvedTbody.insertAdjacentHTML('beforeend', data.html);
                    }
                    this.approvedNextPageUrl = data.next_page_url || null;
                    this.approvedLoadedTo = data.to || this.approvedLoadedTo;
                    this.approvedTotal = data.total ?? this.approvedTotal;
                } catch (error) {
                    console.error(error);
                } finally {
                    this.approvedLoading = false;
                }
            },
        }"
        x-init="$nextTick(() => setupApprovedObserver())"
        @keydown.escape.window="open = false; editOpen = false; bulkOpen = false"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="app-page-eyebrow">Finances</p>
                        <h1 class="app-page-title">Bon de caisse</h1>
                        <p class="app-page-desc max-w-2xl">
                            Suivez toutes les entrées et sorties de caisse avec une référence unique par bon.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="app-btn-primary shrink-0"
                        @click="open = true"
                    >
                        Nouveau bon de caisse
                    </button>
                </div>
            </x-slot>

            <div
                x-show="flashMessage"
                x-cloak
                x-transition
                class="rounded-xl border px-4 py-3 text-sm"
                :class="flashType === 'danger' ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
                role="status"
                x-text="flashMessage"
            ></div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total entrées</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-900" x-text="formatMoney(totalEntries)"></p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Total sorties</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-red-900" x-text="formatMoney(totalExits)"></p>
                </div>
                <div class="rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary">Solde (Entrées - Sorties)</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-neutral-900" x-text="formatMoney(balance)"></p>
                </div>
            </div>
            <p class="text-xs text-neutral-500">
                Totaux et solde : <strong class="font-medium text-neutral-700">bons approuvés uniquement</strong> (avec les filtres date, branche, terminal et type ci-dessous). Les bons en attente sont listés en premier.
            </p>

            <form method="GET" action="{{ route('cash-vouchers.index') }}" class="app-filter-bar grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                <div class="lg:col-span-1">
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div class="lg:col-span-1">
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                @if ($showsBranchFilter)
                    <div class="lg:col-span-1">
                        <label for="filter_branch_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Branche</label>
                        <select
                            id="filter_branch_id"
                            name="branch_id"
                            x-ref="filterBranchSelect"
                            x-model="filterBranchId"
                            @change="onBranchFilterChange()"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                        >
                            <option value="">Toutes</option>
                            @foreach ($branchesForFilter as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="lg:col-span-1">
                    <label for="pos_terminal_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Terminal</label>
                    <select
                        id="pos_terminal_id"
                        name="pos_terminal_id"
                        x-model="filterTerminalId"
                        @change="onTerminalFilterChange()"
                        class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    >
                        <option value="">Tous</option>
                        <template x-for="terminal in filteredTerminals()" :key="terminal.id">
                            <option :value="String(terminal.id)" x-text="terminalOptionLabel(terminal)"></option>
                        </template>
                    </select>
                </div>
                <div class="lg:col-span-1">
                    <label for="type_filter" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</label>
                    <select id="type_filter" name="type" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Tous</option>
                        <option value="entry" @selected(($filters['type'] ?? '') === 'entry')>Entrée</option>
                        <option value="exit" @selected(($filters['type'] ?? '') === 'exit')>Sortie</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="app-btn-primary">Filtrer</button>
                    <a href="{{ route('cash-vouchers.index') }}" class="app-btn-secondary">Réinitialiser</a>
                </div>
            </form>

            <section class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold text-neutral-900">En attente</h2>
                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800" x-text="pendingCount"></span>
                </div>
                <div class="app-table-shell border-amber-200/80 ring-1 ring-amber-100">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        @include('cash_vouchers.partials.table-head', ['selectable' => false])
                        <tbody id="pending-vouchers-body" class="divide-y divide-neutral-100">
                            @forelse ($pendingVouchers as $voucher)
                                @include('cash_vouchers.partials.row', ['voucher' => $voucher])
                            @empty
                                <tr data-empty>
                                    <td colspan="{{ $voucherTableColspan }}" class="px-4 py-8 text-center text-neutral-500">Aucun bon en attente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-8 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-neutral-900">Approuvés</h2>
                        <span
                            x-show="selectedVoucherIds.length > 0"
                            x-cloak
                            class="inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
                            x-text="selectedVoucherIds.length + ' sélectionné' + (selectedVoucherIds.length > 1 ? 's' : '')"
                        ></span>
                    </div>
                    @if (auth()->user()?->hasApplicationAdminAccess())
                        <button
                            type="button"
                            class="app-btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selectedVoucherIds.length === 0"
                            @click="openBulkAssignment()"
                        >
                            Changer branche / terminal
                        </button>
                    @endif
                </div>
                <div class="app-table-scroll-panel app-table-scroll-panel--20-rows" x-ref="approvedTableScroll">
                    <table class="app-table-sticky-first-col min-w-full divide-y divide-neutral-200 text-sm">
                        @include('cash_vouchers.partials.table-head', ['selectable' => $canBulkAssignCashVouchers])
                        <tbody id="approved-vouchers-body" class="divide-y divide-neutral-100" x-ref="approvedTbody">
                            @include('cash_vouchers.partials.approved-rows', [
                                'approvedVouchers' => $approvedVouchers,
                                'voucherTableColspan' => $approvedVoucherTableColspan,
                                'selectable' => $canBulkAssignCashVouchers,
                            ])
                        </tbody>
                    </table>
                    <div x-ref="approvedSentinel" class="h-8 w-full" aria-hidden="true"></div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-600" x-text="approvedStatusLabel()"></p>
                    <p class="text-sm text-neutral-500" x-show="approvedLoading" x-cloak>Chargement…</p>
                    <p class="text-sm text-neutral-500" x-show="!approvedLoading && !approvedNextPageUrl && approvedTotal > 0" x-cloak>Fin de la liste</p>
                </div>
            </section>
        </x-caisse-flow>

        <div
            x-show="bulkOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-transition.opacity
        >
            <div class="absolute inset-0 bg-black/50" @click="bulkOpen = false" aria-hidden="true"></div>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="cash-voucher-bulk-title"
                class="relative z-10 w-full max-w-md rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5"
                @click.stop
            >
                <h2 id="cash-voucher-bulk-title" class="text-lg font-semibold text-neutral-900">Changer la branche et le terminal</h2>
                <p class="mt-1 text-sm text-neutral-600">
                    <span x-text="selectedVoucherIds.length"></span>
                    bon<span x-show="selectedVoucherIds.length > 1">s</span> approuvé<span x-show="selectedVoucherIds.length > 1">s</span> seront réaffectés.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="bulk_branch_id" class="block text-xs font-semibold text-neutral-700">Branche cible</label>
                        <select
                            id="bulk_branch_id"
                            x-model="bulkBranchId"
                            @change="onBulkBranchChange()"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            required
                        >
                            <option value="">Choisir une branche</option>
                            @foreach ($branchesForFilter as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="bulk_pos_terminal_id" class="block text-xs font-semibold text-neutral-700">Terminal cible</label>
                        <select
                            id="bulk_pos_terminal_id"
                            x-model="bulkTerminalId"
                            :disabled="! bulkBranchId"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary disabled:bg-neutral-100"
                            required
                        >
                            <option value="">Choisir un terminal</option>
                            <template x-for="terminal in bulkFilteredTerminals()" :key="terminal.id">
                                <option :value="String(terminal.id)" x-text="terminal.name"></option>
                            </template>
                        </select>
                    </div>

                    <p
                        x-show="bulkError"
                        x-cloak
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900"
                        role="alert"
                        x-text="bulkError"
                    ></p>

                    <p class="text-xs text-neutral-500">Les bons déjà comptabilisés ne peuvent pas être sélectionnés.</p>

                    <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="app-btn-secondary" :disabled="bulkSubmitting" @click="bulkOpen = false">
                            Annuler
                        </button>
                        <button
                            type="button"
                            class="app-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="bulkSubmitting || ! bulkBranchId || ! bulkTerminalId"
                            @click="submitBulkAssignment()"
                        >
                            <span x-show="! bulkSubmitting">Confirmer la réaffectation</span>
                            <span x-show="bulkSubmitting" x-cloak>Traitement…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-transition.opacity
        >
            <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="cash-voucher-title"
                class="relative z-10 w-full max-w-xl rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5"
                @click.stop
            >
                <h2 id="cash-voucher-title" class="text-lg font-semibold text-neutral-900">Nouveau bon de caisse</h2>
                <p class="mt-1 text-sm text-neutral-600">Renseignez les détails du bon de caisse.</p>

                <form action="{{ route('cash-vouchers.store') }}" method="POST" class="mt-5 space-y-4">
                    @csrf

                    @if ($showsBranchFilter)
                        <div>
                            <label for="create_branch_id" class="block text-xs font-semibold text-neutral-700">Branche</label>
                            <select id="create_branch_id" name="branch_id" required class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                                <option value="">Choisir une branche</option>
                                @foreach ($branchesForFilter as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>
                    @endif

                    <div>
                        <label for="voucher_no" class="block text-xs font-semibold text-neutral-700">Numéro du bon</label>
                        <input
                            id="voucher_no"
                            name="voucher_no"
                            type="text"
                            value="{{ old('voucher_no') }}"
                            required
                            maxlength="100"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Ex: BC-2026-001"
                        />
                        <x-input-error :messages="$errors->get('voucher_no')" class="mt-2" />
                    </div>

                    <div>
                        <label for="date" class="block text-xs font-semibold text-neutral-700">Date</label>
                        <input
                            id="date"
                            name="date"
                            type="date"
                            value="{{ old('date', now()->toDateString()) }}"
                            required
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                        />
                        <x-input-error :messages="$errors->get('date')" class="mt-2" />
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-semibold text-neutral-700">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            required
                            maxlength="2000"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Motif du bon de caisse"
                        >{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <p class="block text-xs font-semibold text-neutral-700">Type</p>
                        <div class="mt-2 flex flex-wrap items-center gap-5">
                            <label class="inline-flex items-center gap-2 text-sm text-neutral-800">
                                <input type="radio" name="type" value="entry" @checked(old('type', 'entry') === 'entry') class="border-neutral-300 text-primary focus:ring-primary" required>
                                <span>Entrée</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-neutral-800">
                                <input type="radio" name="type" value="exit" @checked(old('type') === 'exit') class="border-neutral-300 text-primary focus:ring-primary" required>
                                <span>Sortie</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div>
                        <label for="amount" class="block text-xs font-semibold text-neutral-700">Montant</label>
                        <input
                            id="amount"
                            name="amount"
                            type="number"
                            value="{{ old('amount') }}"
                            required
                            min="0.01"
                            step="0.01"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="0.00"
                        />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="open = false">
                            Annuler
                        </button>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            x-show="editOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-transition.opacity
        >
            <div class="absolute inset-0 bg-black/50" @click="editOpen = false" aria-hidden="true"></div>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="cash-voucher-edit-title"
                class="relative z-10 w-full max-w-md rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xl ring-1 ring-neutral-900/5"
                @click.stop
            >
                <h2 id="cash-voucher-edit-title" class="text-lg font-semibold text-neutral-900">Modifier le n° bon</h2>
                <p class="mt-1 text-sm text-neutral-600">Bon en attente - seul le numéro peut être modifié.</p>

                <form :action="editAction" method="POST" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="edit_voucher_id" :value="editVoucherId">

                    <div>
                        <label for="edit_voucher_no" class="block text-xs font-semibold text-neutral-700">Numéro du bon</label>
                        <input
                            id="edit_voucher_no"
                            name="voucher_no"
                            type="text"
                            x-model="editVoucherNo"
                            required
                            maxlength="100"
                            class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Ex: BC-2026-001"
                        />
                        <x-input-error :messages="$errors->get('voucher_no')" class="mt-2" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50" @click="editOpen = false">
                            Annuler
                        </button>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
