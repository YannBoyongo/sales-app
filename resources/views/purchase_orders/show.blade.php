<x-app-layout>
    <x-slot name="header">{{ $purchaseOrder->reference }}</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Bon de commande</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">{{ $purchaseOrder->reference }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-neutral-600">
                        <span class="font-medium text-neutral-800">Emplacement</span> {{ $purchaseOrder->location->name }}
                        <span class="text-neutral-300">·</span>
                        <span class="font-medium text-neutral-800">Fournisseur</span> {{ $purchaseOrder->supplier ?: '—' }}
                        <span class="text-neutral-300">·</span>
                        <span class="font-medium text-neutral-800">Créé par</span> {{ $purchaseOrder->creator?->name ?? '—' }}
                    </p>
                    <div class="mt-4">
                        @if ($purchaseOrder->status === 'received')
                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Réceptionné</span>
                        @elseif ($purchaseOrder->status === 'partial')
                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Réception partielle</span>
                        @else
                            <span class="inline-flex rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-800">Ouvert</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if (auth()->user()->isAdmin() && ! $purchaseOrder->reception_started)
                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="inline-flex items-center rounded-xl border border-neutral-200/90 bg-white/90 px-4 py-2 text-xs font-semibold text-neutral-800 shadow-sm ring-1 ring-neutral-900/5 backdrop-blur-sm transition hover:border-primary/30 hover:text-primary">Modifier PO</a>
                    @endif
                    <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        Retour aux PO
                    </a>
                </div>
            </div>
        </x-slot>

        <form method="POST" action="{{ route('purchase-orders.receive', $purchaseOrder) }}">
            @csrf
            <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="px-4 py-3">Produit</th>
                            <th class="px-4 py-3 text-right">Commandé</th>
                            <th class="px-4 py-3 text-right">Reçu</th>
                            <th class="px-4 py-3 text-right">Restant</th>
                            <th class="px-4 py-3 text-right">Réceptionner maintenant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($purchaseOrder->items as $item)
                            @php $remaining = max(0, $item->quantity_ordered - $item->quantity_received); @endphp
                            <tr class="hover:bg-neutral-50/50">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $item->product->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity_ordered }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity_received }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $remaining }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        type="number"
                                        name="receive[{{ $item->id }}]"
                                        min="0"
                                        max="{{ $remaining }}"
                                        value="0"
                                        class="w-28 rounded-lg border-neutral-300 text-right text-sm shadow-sm focus:border-primary focus:ring-primary"
                                        @disabled($remaining === 0)
                                    />
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-right">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                    Enregistrer la réception
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>

        <section class="mt-8 overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
            <div class="border-b border-neutral-100 bg-neutral-50/80 px-5 py-4">
                <h2 class="text-sm font-semibold text-neutral-900">Historique des réceptions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Produit</th>
                            <th class="px-4 py-3">Emplacement</th>
                            <th class="px-4 py-3 text-right">Qté reçue</th>
                            <th class="px-4 py-3">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($receptions as $reception)
                            <tr class="hover:bg-neutral-50/50">
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-600">{{ $reception->received_at->translatedFormat('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $reception->product->name }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $reception->location->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $reception->quantity }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $reception->receiver?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-neutral-500">Aucune réception enregistrée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-100 px-4 py-3">{{ $receptions->links() }}</div>
        </section>
    </x-caisse-flow>
</x-app-layout>
