<x-app-layout>
    <x-slot name="header">{{ $purchaseOrder->reference }}</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">{{ $purchaseOrder->reference }}</h1>
            <p class="mt-1 text-sm text-neutral-600">
                Emplacement: {{ $purchaseOrder->location->name }} ·
                Fournisseur: {{ $purchaseOrder->supplier ?: '—' }} ·
                Créé par {{ $purchaseOrder->creator?->name ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if (auth()->user()->is_admin && ! $purchaseOrder->reception_started)
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Modifier PO</a>
            @endif
            <a href="{{ route('purchase-orders.index') }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Retour aux PO</a>
        </div>
    </div>

    <div class="mb-4">
        @if ($purchaseOrder->status === 'received')
            <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Réceptionné</span>
        @elseif ($purchaseOrder->status === 'partial')
            <span class="rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Réception partielle</span>
        @else
            <span class="rounded bg-neutral-200 px-2 py-1 text-xs font-semibold text-neutral-800">Ouvert</span>
        @endif
    </div>

    <form method="POST" action="{{ route('purchase-orders.receive', $purchaseOrder) }}">
        @csrf
        <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
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
                        <tr>
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
                                    class="w-28 rounded-md border-neutral-300 text-right shadow-sm focus:border-primary focus:ring-primary"
                                    @disabled($remaining === 0)
                                />
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-right">
                            <button type="submit" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                Enregistrer la réception
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </form>

    <section class="mt-6 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3">
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
                        <tr>
                            <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $reception->received_at->translatedFormat('d/m/Y H:i') }}</td>
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
        <div class="border-t border-neutral-200 px-4 py-3">{{ $receptions->links() }}</div>
    </section>
</x-app-layout>
