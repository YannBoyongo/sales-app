<x-app-layout>
    <x-slot name="header">Mouvements de stock</x-slot>

    <x-page-header title="Mouvements de stock" />

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Produit</th>
                    <th class="px-4 py-3 text-right">Qté</th>
                    <th class="px-4 py-3">Détail</th>
                    <th class="px-4 py-3">Utilisateur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($movements as $m)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">
                            @if ($m->occurred_on)
                                <span title="Date du transfert">{{ $m->occurred_on->translatedFormat('d/m/Y') }}</span>
                                <span class="block text-xs text-neutral-400">Saisi {{ $m->created_at->translatedFormat('d/m/Y H:i') }}</span>
                            @else
                                {{ $m->created_at->translatedFormat('d/m/Y H:i') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @switch($m->type)
                                @case('entry') <span class="rounded bg-neutral-200 px-2 py-0.5 text-xs font-medium">Entrée</span> @break
                                @case('exit') <span class="rounded bg-neutral-200 px-2 py-0.5 text-xs font-medium">Sortie</span> @break
                                @case('transfer') <span class="rounded bg-neutral-200 px-2 py-0.5 text-xs font-medium">Transfert</span> @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $m->product->name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $m->quantity }}</td>
                        <td class="px-4 py-3 text-neutral-600 text-xs max-w-xs truncate">
                            @if ($m->type === 'entry')
                                → {{ $m->toLocation?->name }}
                            @elseif ($m->type === 'exit')
                                ← {{ $m->fromLocation?->name }}
                            @else
                                {{ $m->fromLocation?->name }} → {{ $m->toLocation?->name }}
                                @if ($m->stock_transfer_id)
                                    — <a href="{{ route('stock-transfers.show', $m->stock_transfer_id) }}" class="text-primary underline-offset-2 hover:underline">Transfert #{{ $m->stock_transfer_id }}</a>
                                @endif
                            @endif
                            @if ($m->notes) — {{ $m->notes }} @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-600">{{ $m->user->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-app-layout>
