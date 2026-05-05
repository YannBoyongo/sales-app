<x-app-layout>
    <x-slot name="header">Bons de commande</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Achats</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Bons de commande</h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                        Suivez les commandes fournisseurs, réceptionnez les quantités sur l’emplacement choisi et consultez l’historique des réceptions.
                    </p>
                </div>
                @if (auth()->user()->isAdmin())
                    <a
                        href="{{ route('purchase-orders.create') }}"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        Nouveau PO
                    </a>
                @endif
            </div>
        </x-slot>

        <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
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
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $po->reference }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $po->location->name }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $po->supplier ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($po->status === 'received')
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Réceptionné</span>
                                @elseif ($po->status === 'partial')
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Partiel</span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-800">Ouvert</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $po->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    @if (auth()->user()->isAdmin() && ! $po->reception_started)
                                        <a href="{{ route('purchase-orders.edit', $po) }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">Modifier</a>
                                    @endif
                                    <a href="{{ route('purchase-orders.show', $po) }}" class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">Voir</a>
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
    </x-caisse-flow>
</x-app-layout>
