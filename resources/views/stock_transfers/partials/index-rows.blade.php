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
        <td class="px-4 py-3 text-neutral-700">{{ $t->fromLocation?->name ?? '—' }} <span class="text-neutral-400">({{ $t->fromLocation?->branch?->name ?? '—' }})</span></td>
        <td class="px-4 py-3 text-neutral-700">{{ $t->toLocation?->name ?? '—' }} <span class="text-neutral-400">({{ $t->toLocation?->branch?->name ?? '—' }})</span></td>
        <td class="px-4 py-3 text-neutral-700">
            @if ($t->items->isEmpty())
                <span class="text-neutral-400">—</span>
            @else
                <ul class="space-y-0.5 text-xs leading-relaxed">
                    @foreach ($t->items as $line)
                        <li>
                            <span class="font-medium text-neutral-900">{{ $line->product?->name ?? 'Produit introuvable' }}</span>
                            <span class="tabular-nums text-neutral-600">× {{ $line->quantity }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </td>
        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $t->user?->name ?? '—' }}</td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <a href="{{ route('stock-transfers.show', $t) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">Détail</a>
        </td>
    </tr>
@empty
    <tr data-empty-row="1">
        <td colspan="9" class="px-4 py-8 text-center text-neutral-500">
            @if (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) || ($filters['status'] ?? null) || ($filters['transfer_scope'] ?? null) || ($filters['from_location_id'] ?? null) || ($filters['to_location_id'] ?? null))
                Aucun transfert pour ces critères.
            @else
                Aucun transfert pour le moment.
            @endif
        </td>
    </tr>
@endforelse
