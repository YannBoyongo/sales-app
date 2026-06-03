<x-app-layout>
    <x-slot name="header">Transferts de stock</x-slot>

    <x-page-header
        title="Transferts de stock"
        :action="auth()->user()?->isInventoryReadOnly() ? null : 'Nouveau transfert'"
        :action-href="auth()->user()?->isInventoryReadOnly() ? null : route('stock-transfers.create')"
    />

    @if (session('success'))
        <div class="mb-4 rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('stock-transfers.index') }}" class="app-filter-bar grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
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
            <select id="transfer_scope" name="transfer_scope" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                <option value="">Tous</option>
                <option value="{{ \App\Models\StockTransfer::SCOPE_INTERNAL }}" @selected(($filters['transfer_scope'] ?? '') === \App\Models\StockTransfer::SCOPE_INTERNAL)>{{ \App\Models\StockTransfer::scopeLabel(\App\Models\StockTransfer::SCOPE_INTERNAL) }}</option>
                <option value="{{ \App\Models\StockTransfer::SCOPE_EXTERNAL }}" @selected(($filters['transfer_scope'] ?? '') === \App\Models\StockTransfer::SCOPE_EXTERNAL)>{{ \App\Models\StockTransfer::scopeLabel(\App\Models\StockTransfer::SCOPE_EXTERNAL) }}</option>
            </select>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
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
            <tbody class="divide-y divide-neutral-100">
                @forelse ($transfers as $t)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900 tabular-nums whitespace-nowrap">#{{ $t->id }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($t->isPending())
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_PENDING) }}</span>
                            @elseif ($t->isCancelled())
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-900">{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_CANCELLED) }}</span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900">{{ \App\Models\StockTransfer::statusLabel(\App\Models\StockTransfer::STATUS_CONFIRMED) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-700 whitespace-nowrap">{{ \App\Models\StockTransfer::scopeLabel($t->transfer_scope ?? \App\Models\StockTransfer::SCOPE_INTERNAL) }}</td>
                        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $t->transferred_at->translatedFormat('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-neutral-700">{{ $t->fromLocation->name }} <span class="text-neutral-400">({{ $t->fromLocation->branch->name }})</span></td>
                        <td class="px-4 py-3 text-neutral-700">{{ $t->toLocation->name }} <span class="text-neutral-400">({{ $t->toLocation->branch->name }})</span></td>
                        <td class="px-4 py-3 text-neutral-700">
                            @if ($t->items->isEmpty())
                                <span class="text-neutral-400">—</span>
                            @else
                                <ul class="space-y-0.5 text-xs leading-relaxed">
                                    @foreach ($t->items as $line)
                                        <li>
                                            <span class="font-medium text-neutral-900">{{ $line->product->name }}</span>
                                            <span class="tabular-nums text-neutral-600">× {{ $line->quantity }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $t->user->name }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('stock-transfers.show', $t) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">Détail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-neutral-500">
                            @if (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) || ($filters['status'] ?? null) || ($filters['transfer_scope'] ?? null))
                                Aucun transfert pour ces critères.
                            @else
                                Aucun transfert pour le moment.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($transfers->hasPages() || $transfers->total() > 0)
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-neutral-600">
                @if ($transfers->total() > 0)
                    Affichage de {{ $transfers->firstItem() }} à {{ $transfers->lastItem() }} sur {{ $transfers->total() }} transfert{{ $transfers->total() > 1 ? 's' : '' }}
                @else
                    0 transfert
                @endif
            </p>
            {{ $transfers->links() }}
        </div>
    @endif
</x-app-layout>
