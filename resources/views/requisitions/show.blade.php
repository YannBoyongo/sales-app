<x-app-layout>
    <x-slot name="header">{{ $requisition->reference }}</x-slot>

    <div
        x-data="{
            canEdit: @js($canEditItems),
            expenses: @js((float) old('expenses', $requisition->expenses ?? 0)),
            items: @js($requisitionItems).map((item, index) => ({
                ...item,
                _uid: 'req-' + String(item.product_id) + '-' + index + '-' + Math.random().toString(36).slice(2, 8),
            })),
            itemKey(item) {
                return item._uid;
            },
            money(value) {
                const n = Number(value || 0);
                return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            lineMerchandise(item) {
                return Math.round((Number(item.quantity || 0) * Number(item.unit_price || 0)) * 100) / 100;
            },
            totalMerchandise() {
                return this.items.reduce((sum, item) => sum + this.lineMerchandise(item), 0);
            },
            sharePercent(item) {
                const total = this.totalMerchandise();
                if (total > 0) {
                    return Math.round((this.lineMerchandise(item) / total) * 10000) / 100;
                }
                return this.items.length ? Math.round((100 / this.items.length) * 100) / 100 : 0;
            },
            allocate() {
                const expensesCents = Math.round(Number(this.expenses || 0) * 100);
                const rows = this.items.map((item) => {
                    const merchandise = this.lineMerchandise(item);
                    return {
                        item,
                        merchandise,
                        merchandiseCents: Math.round(merchandise * 100),
                    };
                });
                const totalCents = rows.reduce((sum, row) => sum + row.merchandiseCents, 0);
                let allocated = 0;

                rows.forEach((row, index) => {
                    const isLast = index === rows.length - 1;
                    let otherCents = 0;

                    if (expensesCents <= 0 || rows.length === 0) {
                        otherCents = 0;
                    } else if (totalCents <= 0) {
                        const base = Math.floor(expensesCents / rows.length);
                        const remainder = expensesCents - (base * rows.length);
                        otherCents = base + (index < remainder ? 1 : 0);
                    } else if (isLast) {
                        otherCents = Math.max(0, expensesCents - allocated);
                    } else {
                        otherCents = Math.round(expensesCents * (row.merchandiseCents / totalCents));
                        allocated += otherCents;
                    }

                    const other = Math.round(otherCents) / 100;
                    const tax = Math.round(Number(row.item.tax || 0) * 100) / 100;
                    const qty = Math.max(1, Number(row.item.quantity || 1));
                    const costTotal = Math.round((row.merchandise + tax + other) * 100) / 100;
                    row.item.other = other;
                    row.item.cost = costTotal;
                    row.item.cost_total = costTotal;
                    row.item.unit_cost = Math.round((costTotal / qty) * 100) / 100;
                    row.item.share_percent = this.sharePercent(row.item);
                    row.item.merchandise = row.merchandise;
                });
            },
            unitCost(item) {
                const qty = Math.max(1, Number(item.quantity || 1));
                const total = Number(item.cost_total ?? item.cost ?? 0);
                return Math.round((total / qty) * 100) / 100;
            },
            costTotal(item) {
                return Number(item.cost_total ?? item.cost ?? 0);
            },
            removeItem(index) {
                if (! this.canEdit) {
                    return;
                }
                this.items.splice(index, 1);
                this.allocate();
            },
            submittingAction: null,
            init() {
                this.allocate();
                this.$watch('expenses', () => this.allocate());
            },
        }"
        class="space-y-6"
    >
        <x-caisse-flow max-width="max-w-7xl" :with-card="false">
            <x-slot name="header">
                <div class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="app-page-eyebrow">Achats</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2.5">
                                <h1 class="app-page-title mt-0">{{ $requisition->reference }}</h1>
                                @if ($requisition->status === \App\Models\Requisition::STATUS_CONFIRMED)
                                    <span class="app-badge-success">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->status === \App\Models\Requisition::STATUS_ORDERED)
                                    <span class="app-badge-info">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->status === \App\Models\Requisition::STATUS_REJECTED)
                                    <span class="app-badge-danger">{{ $requisition->statusLabel() }}</span>
                                @elseif ($requisition->isEditable())
                                    <span class="app-badge-warning">{{ $requisition->statusLabel() }}</span>
                                @else
                                    <span class="app-badge-neutral">{{ $requisition->statusLabel() }}</span>
                                @endif
                            </div>
                            <p class="app-page-desc mt-1">
                                Créé par <span class="font-medium text-neutral-800">{{ $requisition->creator?->name ?? '-' }}</span>
                                @if ($requisition->purchaseOrders->isNotEmpty())
                                    <span class="text-neutral-300">·</span>
                                    PO
                                    @foreach ($requisition->purchaseOrders as $po)
                                        <a href="{{ route('purchase-orders.show', $po) }}" class="font-medium text-primary hover:underline">{{ $po->reference }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($canEditItems)
                                <a href="{{ route('requisitions.edit', $requisition) }}" class="app-btn-secondary">Ajouter des articles</a>
                                <form
                                    action="{{ route('requisitions.destroy', $requisition) }}"
                                    method="POST"
                                    onsubmit="return confirm('Supprimer définitivement cette réquisition ?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="app-btn-danger">Supprimer</button>
                                </form>
                            @endif
                            <a href="{{ route('requisitions.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                Retour
                            </a>
                        </div>
                    </div>

                    @if ($canEditItems)
                        <div class="rounded-xl border border-white/70 bg-white/70 p-3 shadow-sm sm:p-4">
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,11rem)_minmax(0,9rem)_1fr] sm:items-start">
                                <div>
                                    <label for="requisition_date" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Date</label>
                                    <input
                                        id="requisition_date"
                                        form="requisition-items-form"
                                        name="date"
                                        type="date"
                                        value="{{ old('date', $requisition->date?->toDateString()) }}"
                                        required
                                        class="mt-1.5 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                    />
                                    <x-input-error :messages="$errors->get('date')" class="mt-1.5" />
                                </div>
                                <div>
                                    <label for="requisition_expenses" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Frais (USD)</label>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-neutral-400">$</span>
                                        <input
                                            id="requisition_expenses"
                                            form="requisition-items-form"
                                            name="expenses"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            x-model.number="expenses"
                                            class="block w-full rounded-lg border-neutral-300 py-2 pl-7 pr-3 text-sm tabular-nums shadow-sm focus:border-primary focus:ring-primary"
                                        />
                                    </div>
                                    <x-input-error :messages="$errors->get('expenses')" class="mt-1.5" />
                                </div>
                                <div class="flex items-end sm:min-h-[2.625rem] sm:pb-0.5">
                                    <p class="text-xs leading-snug text-neutral-500">
                                        Frais partagés (transport, hôtel, voyage…) répartis au prorata sur chaque article.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-x-6 gap-y-2 rounded-xl border border-white/70 bg-white/70 px-4 py-3 text-sm shadow-sm">
                            <p class="text-neutral-600">
                                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Date</span>
                                <span class="mt-0.5 block font-medium text-neutral-900">{{ $requisition->date?->format('d/m/Y') ?? '-' }}</span>
                            </p>
                            <p class="text-neutral-600">
                                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Frais</span>
                                <span class="mt-0.5 block font-medium tabular-nums text-neutral-900">{{ \App\Support\Money::usd($requisition->expenses) }}</span>
                            </p>
                        </div>
                    @endif

                    @if (! empty($canConvertToPo) && auth()->user()?->hasApplicationAdminAccess())
                        <div class="rounded-xl border border-primary/20 bg-white/80 p-4 shadow-sm">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h2 class="text-sm font-semibold text-neutral-900">Créer un bon de commande</h2>
                                    <p class="mt-1 text-xs text-neutral-500">
                                        Copie les articles, lots et coûts unitaires. Après réception approuvée, le stock gardera ces lots.
                                    </p>
                                </div>
                                <form
                                    action="{{ route('requisitions.convert-to-po', $requisition) }}"
                                    method="POST"
                                    class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:grid-cols-[minmax(0,14rem)_minmax(0,12rem)_auto]"
                                    onsubmit="return confirm('Créer le bon de commande depuis cette réquisition ?');"
                                >
                                    @csrf
                                    <div>
                                        <label for="convert_location_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Emplacement de réception</label>
                                        <select
                                            id="convert_location_id"
                                            name="location_id"
                                            required
                                            class="mt-1.5 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                        >
                                            <option value="">- Choisir -</option>
                                            @foreach ($receptionLocations as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->branch?->name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="convert_supplier" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Fournisseur</label>
                                        <input
                                            id="convert_supplier"
                                            name="supplier"
                                            type="text"
                                            class="mt-1.5 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                        />
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" class="app-btn-primary w-full sm:w-auto">Créer le PO</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </x-slot>

            <section class="app-panel overflow-hidden">
                <div class="app-panel-header flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-900">Articles de la réquisition</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            {{ $canEditItems
                                ? 'Même produit possible plusieurs fois si le N° lot diffère. Autre / coûts se calculent via les frais.'
                                : 'Liste des articles, lots et coûts enregistrés.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-800" x-text="items.length + ' article(s)'"></span>
                        <span class="inline-flex rounded-full bg-white px-2.5 py-0.5 text-xs font-medium tabular-nums text-neutral-700 ring-1 ring-neutral-200">
                            Marchandises $<span x-text="money(totalMerchandise())"></span>
                        </span>
                        <span class="inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tabular-nums text-primary ring-1 ring-primary/20">
                            Frais $<span x-text="money(expenses)"></span>
                        </span>
                    </div>
                </div>

                <form
                    id="requisition-items-form"
                    action="{{ route('requisitions.items.sync', $requisition) }}"
                    method="POST"
                    @submit="
                        if (! canEdit) { return; }
                        if (submittingAction === 'confirm') {
                            if (! items.length) { $event.preventDefault(); alert('Ajoutez au moins un article avant de confirmer.'); submittingAction = null; return; }
                            if (! confirm('Confirmer cette réquisition ? Elle ne pourra plus être modifiée.')) { $event.preventDefault(); submittingAction = null; }
                            return;
                        }
                        if (! confirm('Enregistrer le brouillon (statut En attente) ?')) { $event.preventDefault(); submittingAction = null; }
                    "
                >
                    @csrf
                    <input type="hidden" name="confirm" :value="submittingAction === 'confirm' ? 1 : 0">

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                                <tr>
                                    <th class="px-3 py-3 sm:px-4">Produit</th>
                                    <th class="px-3 py-3 sm:px-4">N° lot</th>
                                    <th class="px-3 py-3 text-right sm:px-4">Qté</th>
                                    <th class="px-3 py-3 text-right sm:px-4">P.U.</th>
                                    <th class="px-3 py-3 text-right sm:px-4">Taxe</th>
                                    <th class="px-3 py-3 text-right sm:px-4">Part %</th>
                                    <th class="px-3 py-3 text-right sm:px-4">Autre</th>
                                    <th class="px-3 py-3 text-right sm:px-4">Coût unit.</th>
                                    <th class="px-3 py-3 text-right sm:px-4">Coût total</th>
                                    @if ($canEditItems)
                                        <th class="px-3 py-3 text-right sm:px-4">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <template x-for="(item, index) in items" :key="itemKey(item)">
                                    <tr class="transition-colors hover:bg-neutral-50/80">
                                        <td class="px-3 py-3 sm:px-4">
                                            <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                            <div class="font-medium text-neutral-900" x-text="item.product_name"></div>
                                            <div class="text-xs text-neutral-500" x-show="item.product_sku" x-text="item.product_sku"></div>
                                            <div class="mt-0.5 text-xs tabular-nums text-neutral-400">
                                                Marchandises : $<span x-text="money(lineMerchandise(item))"></span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 sm:px-4">
                                            @if ($canEditItems)
                                                <input
                                                    type="text"
                                                    maxlength="100"
                                                    :name="'items[' + index + '][batch_number]'"
                                                    x-model="item.batch_number"
                                                    class="block w-28 rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                                                >
                                            @else
                                                <span class="text-neutral-800" x-text="item.batch_number || '-'"></span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right sm:px-4">
                                            @if ($canEditItems)
                                                <input
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    :name="'items[' + index + '][quantity]'"
                                                    x-model.number="item.quantity"
                                                    @input="allocate()"
                                                    class="ml-auto block w-20 rounded-lg border-neutral-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary"
                                                >
                                            @else
                                                <span class="tabular-nums font-medium text-neutral-900" x-text="item.quantity"></span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right sm:px-4">
                                            @if ($canEditItems)
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    :name="'items[' + index + '][unit_price]'"
                                                    x-model.number="item.unit_price"
                                                    @input="allocate()"
                                                    class="ml-auto block w-24 rounded-lg border-neutral-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary"
                                                >
                                            @else
                                                <span class="tabular-nums" x-text="'$' + money(item.unit_price)"></span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right sm:px-4">
                                            @if ($canEditItems)
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    :name="'items[' + index + '][tax]'"
                                                    x-model.number="item.tax"
                                                    @input="allocate()"
                                                    class="ml-auto block w-24 rounded-lg border-neutral-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary"
                                                >
                                            @else
                                                <span class="tabular-nums" x-text="'$' + money(item.tax)"></span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right tabular-nums text-neutral-600 sm:px-4">
                                            <span x-text="sharePercent(item).toFixed(2) + '%'"></span>
                                        </td>
                                        <td class="px-3 py-3 text-right tabular-nums text-neutral-800 sm:px-4">
                                            <input type="hidden" :name="'items[' + index + '][other]'" :value="item.other">
                                            $<span x-text="money(item.other)"></span>
                                        </td>
                                        <td class="px-3 py-3 text-right tabular-nums text-neutral-800 sm:px-4">
                                            $<span x-text="money(unitCost(item))"></span>
                                        </td>
                                        <td class="px-3 py-3 text-right tabular-nums font-semibold text-neutral-900 sm:px-4">
                                            <input type="hidden" :name="'items[' + index + '][cost]'" :value="costTotal(item)">
                                            $<span x-text="money(costTotal(item))"></span>
                                        </td>
                                        @if ($canEditItems)
                                            <td class="px-3 py-3 text-right sm:px-4">
                                                <button
                                                    type="button"
                                                    class="text-xs font-semibold text-red-700 hover:text-red-800"
                                                    @click="removeItem(index)"
                                                >
                                                    Retirer
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0">
                                    <td colspan="{{ $canEditItems ? 10 : 9 }}" class="px-4 py-10 text-center text-neutral-500 sm:px-5">
                                        @if ($canEditItems)
                                            Aucun article. <a href="{{ route('requisitions.edit', $requisition) }}" class="font-medium text-primary hover:underline">Ajouter des articles</a>
                                        @else
                                            Aucun article dans cette réquisition.
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot x-show="items.length > 0" class="border-t border-neutral-200 bg-slate-50/80 text-sm">
                                <tr>
                                    <td colspan="5" class="px-3 py-3 text-right font-medium text-neutral-700 sm:px-4">Totaux</td>
                                    <td class="px-3 py-3 text-right tabular-nums text-neutral-600 sm:px-4">100%</td>
                                    <td class="px-3 py-3 text-right tabular-nums font-medium text-neutral-800 sm:px-4">
                                        $<span x-text="money(items.reduce((s, i) => s + Number(i.other || 0), 0))"></span>
                                    </td>
                                    <td class="px-3 py-3 sm:px-4"></td>
                                    <td class="px-3 py-3 text-right tabular-nums font-semibold text-neutral-900 sm:px-4">
                                        $<span x-text="money(items.reduce((s, i) => s + costTotal(i), 0))"></span>
                                    </td>
                                    @if ($canEditItems)
                                        <td></td>
                                    @endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($canEditItems)
                        <div class="border-t border-neutral-100 bg-slate-50/80 px-4 py-4 sm:px-5">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <p class="text-xs text-neutral-500">
                                    Enregistrer garde le statut <span class="font-medium">En attente</span>.
                                    Confirmer verrouille la réquisition.
                                </p>
                                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                    <button
                                        type="submit"
                                        class="app-btn-secondary w-full sm:w-auto"
                                        @click="submittingAction = 'save'"
                                    >
                                        Enregistrer
                                    </button>
                                    <button
                                        type="submit"
                                        class="app-btn-primary w-full sm:w-auto"
                                        @click="submittingAction = 'confirm'"
                                    >
                                        Confirmer
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </form>
            </section>
        </x-caisse-flow>
    </div>
</x-app-layout>
