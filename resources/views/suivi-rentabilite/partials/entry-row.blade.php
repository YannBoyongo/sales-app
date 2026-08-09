<tr
    data-entry-id="{{ $entry->id }}"
    data-occurred-on="{{ $entry->occurred_on->toDateString() }}"
    data-direction="{{ $entry->direction }}"
    data-cost-center-id="{{ $entry->cost_center_id }}"
    data-transaction-type-id="{{ $entry->cost_transaction_type_id }}"
    data-amount="{{ $entry->amount }}"
    data-description="{{ e($entry->description ?? '') }}"
>
    <td class="whitespace-nowrap px-4 py-3 text-neutral-700">{{ $entry->occurred_on->translatedFormat('d/m/Y') }}</td>
    <td class="px-4 py-3 text-neutral-900">{{ $entry->costCenter->name }}</td>
    <td class="px-4 py-3 text-neutral-700">{{ $entry->transactionType->name }}</td>
    <td class="px-4 py-3 text-neutral-700">{{ $entry->description ?: '-' }}</td>
    <td class="px-4 py-3 text-right tabular-nums">
        @if ($entry->isEntry())
            <span class="font-medium text-emerald-700">{{ \App\Support\Money::usd($entry->amount) }}</span>
        @else
            <span class="text-neutral-400">-</span>
        @endif
    </td>
    <td class="px-4 py-3 text-right tabular-nums">
        @if (! $entry->isEntry())
            <span class="font-medium text-red-700">{{ \App\Support\Money::usd($entry->amount) }}</span>
        @else
            <span class="text-neutral-400">-</span>
        @endif
    </td>
    <td class="px-4 py-3 text-right tabular-nums font-bold text-neutral-900">{{ \App\Support\Money::usd($entry->balance_after) }}</td>
    <td class="px-4 py-3 text-right whitespace-nowrap">
        <button type="button" data-action="edit" class="text-xs font-semibold text-primary hover:underline">Modifier</button>
        <button type="button" data-action="delete" class="ml-3 text-xs font-semibold text-red-700 hover:underline">Supprimer</button>
    </td>
</tr>
