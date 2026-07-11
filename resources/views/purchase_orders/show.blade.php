<x-app-layout>
    <x-slot name="header">{{ $purchaseOrder->reference }}</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Bon de commande</p>
                    <h1 class="app-page-title">{{ $purchaseOrder->reference }}</h1>
                    <p class="app-page-desc max-w-3xl">
                        <span class="font-medium text-neutral-800">Emplacement</span> {{ $purchaseOrder->location?->name ?? '—' }}
                        <span class="text-neutral-300">·</span>
                        <span class="font-medium text-neutral-800">Fournisseur</span> {{ $purchaseOrder->supplier ?: '—' }}
                        <span class="text-neutral-300">·</span>
                        <span class="font-medium text-neutral-800">Créé par</span> {{ $purchaseOrder->creator?->name ?? '—' }}
                    </p>
                    <div class="mt-4">
                        @if ($purchaseOrder->status === 'received')
                            <span class="app-badge-success">Réceptionné</span>
                        @elseif ($purchaseOrder->status === 'partial')
                            <span class="app-badge-warning">Réception partielle</span>
                        @else
                            <span class="app-badge-neutral">Ouvert</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if (auth()->user()->isAdmin() && ! $purchaseOrder->reception_started)
                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="app-btn-secondary">Modifier PO</a>
                    @endif
                    <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        Retour aux PO
                    </a>
                </div>
            </div>
        </x-slot>

        @if (auth()->user()?->isInventoryReadOnly())
            <div class="app-table-shell">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3">Produit</th>
                            <th class="px-4 py-3 text-right">Commandé</th>
                            <th class="px-4 py-3 text-right">Reçu</th>
                            <th class="px-4 py-3 text-right">En attente</th>
                            <th class="px-4 py-3 text-right">Restant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($purchaseOrder->items as $item)
                            @php
                                $pending = (int) ($pendingReceiveByItem[$item->id] ?? 0);
                                $remaining = max(0, $item->quantity_ordered - $item->quantity_received - $pending);
                            @endphp
                            <tr class="hover:bg-neutral-50/50">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $item->product->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity_ordered }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity_received }}</td>
                                <td class="px-4 py-3 text-right tabular-nums @if($pending > 0) text-amber-700 font-medium @endif">{{ $pending }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $remaining }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            @if ($pendingBatches->isNotEmpty())
                <div class="mb-6 space-y-4">
                    @foreach ($pendingBatches as $batch)
                        <div class="app-alert-warning !mb-0">
                            <p class="font-semibold">Réception en attente d’approbation</p>
                            <p class="mt-1 text-amber-900/90">
                                Soumise par {{ $batch->submitter?->name ?? '—' }}
                                le {{ $batch->submitted_at->translatedFormat('d/m/Y à H:i') }}
                            </p>
                            <ul class="mt-3 space-y-1 text-amber-900">
                                @foreach ($batch->receptions as $reception)
                                    <li>{{ $reception->product->name }} : <span class="tabular-nums font-medium">{{ $reception->quantity }}</span></li>
                                @endforeach
                            </ul>
                            @if (auth()->user()->isAdmin())
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <form action="{{ route('purchase-orders.reception-batches.approve', [$purchaseOrder, $batch]) }}" method="POST" onsubmit="return confirm('Approuver cette réception et mettre à jour le stock ?');">
                                        @csrf
                                        <x-primary-button type="submit">Approuver la réception</x-primary-button>
                                    </form>
                                    <form action="{{ route('purchase-orders.reception-batches.reject', [$purchaseOrder, $batch]) }}" method="POST" onsubmit="return confirm('Refuser cette réception ? Les quantités ne seront pas ajoutées au stock.');">
                                        @csrf
                                        <x-secondary-button type="submit">Refuser</x-secondary-button>
                                    </form>
                                </div>
                            @else
                                <p class="mt-3 text-xs text-amber-900/90">Le stock ne sera mis à jour qu’après approbation par un administrateur.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('purchase-orders.receive', $purchaseOrder) }}">
                @csrf
                <div class="app-table-shell">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3">Produit</th>
                                <th class="px-4 py-3 text-right">Commandé</th>
                                <th class="px-4 py-3 text-right">Reçu</th>
                                <th class="px-4 py-3 text-right">En attente</th>
                                <th class="px-4 py-3 text-right">Restant</th>
                                <th class="px-4 py-3 text-right">Réceptionner maintenant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($purchaseOrder->items as $item)
                                @php
                                    $pending = (int) ($pendingReceiveByItem[$item->id] ?? 0);
                                    $remaining = max(0, $item->quantity_ordered - $item->quantity_received - $pending);
                                @endphp
                                <tr class="hover:bg-neutral-50/50">
                                    <td class="px-4 py-3 font-medium text-neutral-900">{{ $item->product->name }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity_ordered }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity_received }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums @if($pending > 0) text-amber-700 font-medium @endif">{{ $pending }}</td>
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
                                <td colspan="6" class="px-4 py-4 text-right">
                                    <button type="submit" class="app-btn-primary">
                                        Soumettre pour approbation
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>
        @endif

        <section class="app-panel mt-8">
            <div class="app-panel-header">
                <h2 class="text-sm font-semibold text-neutral-900">Historique des réceptions</h2>
            </div>
            <div class="app-table-shell border-0 shadow-none">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Produit</th>
                            <th class="px-4 py-3">Emplacement</th>
                            <th class="px-4 py-3 text-right">Qté</th>
                            <th class="px-4 py-3">Soumis par</th>
                            <th class="px-4 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($receptions as $reception)
                            @php
                                $batchStatus = $reception->batch?->status;
                            @endphp
                            <tr class="hover:bg-neutral-50/50">
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-600">{{ $reception->received_at->translatedFormat('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $reception->product->name }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $reception->location?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $reception->quantity }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $reception->receiver?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($batchStatus === \App\Models\PurchaseOrderReceptionBatch::STATUS_PENDING)
                                        <span class="app-badge-warning">En attente</span>
                                    @elseif ($batchStatus === \App\Models\PurchaseOrderReceptionBatch::STATUS_REJECTED)
                                        <span class="app-badge-danger">Refusée</span>
                                    @else
                                        <span class="app-badge-success">Approuvée</span>
                                        @if ($reception->batch?->approver)
                                            <span class="mt-1 block text-xs text-neutral-500">par {{ $reception->batch->approver->name }}</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-neutral-500">Aucune réception enregistrée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-100 px-4 py-3">{{ $receptions->links() }}</div>
        </section>
    </x-caisse-flow>
</x-app-layout>
