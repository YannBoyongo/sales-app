<x-app-layout>
    <x-slot name="header">Mouvements de stock</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Stock</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Mouvements de stock</h1>
                    <p class="mt-3 max-w-3xl text-base leading-relaxed text-neutral-600">
                        Journal des entrées, sorties, transferts et ajustements sur votre périmètre. Les transferts liés à un bon de transfert affichent un lien lorsque vous y avez accès.
                    </p>
                </div>
                <a
                    href="{{ route('stock-movements.create') }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Nouveau mouvement
                </a>
            </div>
        </x-slot>

        <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
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
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600">
                                @if ($m->occurred_on)
                                    <span title="Date du transfert">{{ $m->occurred_on->translatedFormat('d/m/Y') }}</span>
                                    <span class="block text-xs text-neutral-400">Saisi {{ $m->created_at->translatedFormat('d/m/Y H:i') }}</span>
                                @else
                                    {{ $m->created_at->translatedFormat('d/m/Y H:i') }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @switch($m->type)
                                    @case('entry') <span class="inline-flex rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800">Entrée</span> @break
                                    @case('exit') <span class="inline-flex rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800">Sortie</span> @break
                                    @case('transfer') <span class="inline-flex rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800">Transfert</span> @break
                                    @case('adjustment') <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-900">Ajustement</span> @break
                                @endswitch
                            </td>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $m->product->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $m->quantity }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-xs text-neutral-600">
                                @if ($m->type === 'entry')
                                    → {{ $m->toLocation?->name }}
                                @elseif ($m->type === 'exit')
                                    ← {{ $m->fromLocation?->name }}
                                @elseif ($m->type === 'adjustment')
                                    {{ $m->fromLocation?->name ?? '—' }}
                                @else
                                    {{ $m->fromLocation?->name }} → {{ $m->toLocation?->name }}
                                    @if ($m->stock_transfer_id)
                                        @if (auth()->user()->canManageStockTransfers())
                                            — <a href="{{ route('stock-transfers.show', $m->stock_transfer_id) }}" class="text-primary underline-offset-2 hover:underline">Transfert #{{ $m->stock_transfer_id }}</a>
                                        @else
                                            — <span class="text-neutral-500">Transfert #{{ $m->stock_transfer_id }}</span>
                                        @endif
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
    </x-caisse-flow>
</x-app-layout>
