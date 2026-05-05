<x-app-layout>
    <x-slot name="header">Transfert #{{ $stockTransfer->id }}</x-slot>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Transfert #{{ $stockTransfer->id }}</h1>
            <p class="mt-1 text-sm text-neutral-600">Date : {{ $stockTransfer->transferred_at->translatedFormat('d/m/Y') }} — par {{ $stockTransfer->user->name }}</p>
        </div>
        <a href="{{ route('stock-transfers.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">← Liste des transferts</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ session('success') }}</div>
    @endif

    <div class="mb-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Type</dt>
                <dd class="mt-1 text-neutral-900">{{ \App\Models\StockTransfer::scopeLabel($stockTransfer->transfer_scope ?? \App\Models\StockTransfer::SCOPE_INTERNAL) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Source</dt>
                <dd class="mt-1 text-neutral-900">{{ $stockTransfer->fromLocation->name }} <span class="text-neutral-500">({{ $stockTransfer->fromLocation->branch->name }})</span></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Destination</dt>
                <dd class="mt-1 text-neutral-900">{{ $stockTransfer->toLocation->name }} <span class="text-neutral-500">({{ $stockTransfer->toLocation->branch->name }})</span></dd>
            </div>
        </dl>
        @if ($stockTransfer->notes)
            <p class="mt-4 text-sm text-neutral-700"><span class="font-medium">Notes :</span> {{ $stockTransfer->notes }}</p>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Produit</th>
                    <th class="px-4 py-3 text-right">Quantité</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($stockTransfer->items as $line)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $line->product->name }}@if($line->product->sku) <span class="text-neutral-500 font-normal">({{ $line->product->sku }})</span>@endif</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $line->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-neutral-500">Les mouvements correspondants apparaissent dans « Mouvements de stock » (type Transfert) avec la même date comptable.</p>
</x-app-layout>
