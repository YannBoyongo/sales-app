@forelse ($sales as $sale)
    @php($terminal = $sale->posShift?->posTerminal)
    <tr @class([
        'hover:bg-neutral-50/80' => ! $sale->isPendingDiscount(),
        'bg-amber-50/90 hover:bg-amber-100/80' => $sale->isPendingDiscount(),
    ])>
        <td class="px-4 py-3 font-mono text-neutral-800 whitespace-nowrap">{{ $sale->reference }}</td>
        <td class="px-4 py-3 text-neutral-700">
            @if ($terminal?->name)
                {{ $terminal->name }}
                @if ($showsMultipleBranches && $terminal->branch?->name)
                    <span class="block text-xs text-neutral-500">{{ $terminal->branch->name }}</span>
                @endif
            @else
                -
            @endif
        </td>
        <td class="px-4 py-3 text-neutral-700">{{ $sale->displayClientName() ?? '-' }}</td>
        <td class="px-4 py-3 whitespace-nowrap">
            @if ($sale->payment_type === 'credit')
                <span class="app-badge-warning">Crédit</span>
            @elseif ($sale->payment_type === 'caution')
                <span class="app-badge-info">Caution</span>
            @else
                <span class="app-badge-success">Cash</span>
            @endif
        </td>
        <td class="px-4 py-3 text-neutral-700">
            @if ($sale->items->isEmpty())
                <span class="text-neutral-400">-</span>
            @else
                <ul class="space-y-0.5 text-xs leading-relaxed">
                    @foreach ($sale->items as $item)
                        <li>
                            <span class="font-medium text-neutral-900">{{ $item->product->name }}</span>
                            <span class="tabular-nums text-neutral-600">× {{ $item->quantity }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </td>
        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $sale->effectiveSoldAt()->translatedFormat('d/m/Y') }}</td>
        <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($sale->expectedPayableAmount()) }}</td>
        <td class="px-4 py-3 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($sale->paidAmountValue()) }}</td>
        <td class="px-4 py-3 text-right tabular-nums font-medium {{ bccomp($sale->remainingAmountValue(), '0', 2) === 1 ? 'text-amber-800' : 'text-neutral-700' }}">{{ \App\Support\Money::usd($sale->remainingAmountValue()) }}</td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="inline-flex items-center justify-end gap-0.5">
                <a
                    href="{{ route('sales.show', [$sale->branch, $sale]) }}"
                    class="app-icon-btn"
                    title="Voir la vente"
                >
                    <span class="sr-only">Voir</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </a>
            </div>
        </td>
    </tr>
@empty
    <tr data-empty-row="1">
        <td colspan="10" class="px-4 py-10 text-center text-neutral-500">
            @if (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) || ($filters['pos_terminal_id'] ?? null) || ($filters['payment_type'] ?? null))
                Aucune vente pour cette période.
            @else
                Aucune vente enregistrée.
            @endif
        </td>
    </tr>
@endforelse
