<x-app-layout>
    <x-slot name="header">Bons de commande</x-slot>

    <x-page-header
        title="Bons de commande"
        :action="auth()->user()->is_admin ? 'Nouveau PO' : null"
        :action-href="auth()->user()->is_admin ? route('purchase-orders.create') : null"
    />

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Référence</th>
                    <th class="px-4 py-3">Emplacement</th>
                    <th class="px-4 py-3">Fournisseur</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Créé par</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($purchaseOrders as $po)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $po->reference }}</td>
                        <td class="px-4 py-3 text-neutral-700">{{ $po->location->name }}</td>
                        <td class="px-4 py-3 text-neutral-700">{{ $po->supplier ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($po->status === 'received')
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Réceptionné</span>
                            @elseif ($po->status === 'partial')
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Partiel</span>
                            @else
                                <span class="rounded bg-neutral-200 px-2 py-0.5 text-xs font-semibold text-neutral-800">Ouvert</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-700">{{ $po->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                @if (auth()->user()->is_admin && ! $po->reception_started)
                                    <a href="{{ route('purchase-orders.edit', $po) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Modifier</a>
                                @endif
                                <a href="{{ route('purchase-orders.show', $po) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Voir</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Aucun bon de commande.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $purchaseOrders->links() }}</div>
</x-app-layout>
