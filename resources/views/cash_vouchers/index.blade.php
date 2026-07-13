<x-app-layout>
    <x-slot name="header">Bons de caisse</x-slot>

    @php
        $voucherTableColspan = 5
            + (auth()->user()?->isAdmin() ? 1 : 0)
            + (auth()->user()?->canAccessAccounting() ? 1 : 0);
    @endphp

    <div
        x-data="{
            open: {{ ($errors->any() && ! old('edit_voucher_id')) ? 'true' : 'false' }},
            editOpen: {{ old('edit_voucher_id') ? 'true' : 'false' }},
            editVoucherId: @js(old('edit_voucher_id')),
            editVoucherNo: @js(old('voucher_no', '')),
            editAction: @js(old('edit_voucher_id') ? route('cash-vouchers.update', old('edit_voucher_id')) : ''),
            pendingCount: {{ $pendingVouchers->count() }},
            totalEntries: {{ $totalEntries }},
            totalExits: {{ $totalExits }},
            balance: {{ $balance }},
            tableColspan: @js($voucherTableColspan),
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
        }"
        @keydown.escape.window="open = false; editOpen = false"
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
                Totaux et solde : <strong class="font-medium text-neutral-700">bons approuvés uniquement</strong> (avec les filtres date et type ci-dessous). Les bons en attente sont listés en premier.
            </p>

            <form method="GET" action="{{ route('cash-vouchers.index') }}" class="app-filter-bar grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-1">
                    <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date du</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div class="lg:col-span-1">
                    <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date au</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary" />
                </div>
                <div class="lg:col-span-1">
                    <label for="type_filter" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</label>
                    <select id="type_filter" name="type" class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Tous</option>
                        <option value="entry" @selected(($filters['type'] ?? '') === 'entry')>Entrée</option>
                        <option value="exit" @selected(($filters['type'] ?? '') === 'exit')>Sortie</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 lg:col-span-1">
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
                        @include('cash_vouchers.partials.table-head')
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
                <h2 class="text-sm font-semibold text-neutral-900">Approuvés</h2>
                <div class="app-table-shell">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        @include('cash_vouchers.partials.table-head')
                        <tbody id="approved-vouchers-body" class="divide-y divide-neutral-100">
                            @forelse ($approvedVouchers as $voucher)
                                @include('cash_vouchers.partials.row', ['voucher' => $voucher])
                            @empty
                                <tr data-empty>
                                    <td colspan="{{ $voucherTableColspan }}" class="px-4 py-10 text-center text-neutral-500">Aucun bon approuvé.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $approvedVouchers->links() }}</div>
            </section>
        </x-caisse-flow>

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
                <p class="mt-1 text-sm text-neutral-600">Bon en attente — seul le numéro peut être modifié.</p>

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
