<x-app-layout>
    <x-slot name="header">Stocks par emplacement</x-slot>

    <x-page-header title="Stocks par emplacement" />

    <p class="mb-4 text-sm text-neutral-600">
        Une ligne par produit ; chaque colonne correspond à un emplacement. Les cellules en <span class="rounded bg-red-100 px-1 text-red-900">rouge</span> indiquent un stock strictement sous le seuil (emplacement ou seuil global du produit).
    </p>

    @if ($locations->isEmpty())
        <div class="rounded-lg border border-neutral-200 bg-white p-6 text-sm text-neutral-600 shadow-sm">
            Aucun emplacement disponible pour votre compte.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th scope="col" class="sticky left-0 z-20 min-w-[12rem] border-r border-neutral-200 bg-neutral-50 px-4 py-3 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">Produit</th>
                        @foreach ($locations as $loc)
                            <th scope="col" class="min-w-[6.5rem] whitespace-nowrap px-3 py-3 text-right" title="{{ $loc->branch->name }}">
                                <span class="block max-w-[8rem] truncate">{{ $loc->name }}</span>
                                <span class="block max-w-[8rem] truncate font-normal normal-case text-neutral-400">{{ $loc->branch->name }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($products as $product)
                        <tr class="group hover:bg-neutral-50/80">
                            <th scope="row" class="sticky left-0 z-10 border-r border-neutral-200 bg-white px-4 py-3 text-left font-medium text-neutral-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.06)] group-hover:bg-neutral-50/80">
                                <span class="block">{{ $product->name }}</span>
                                @if ($product->sku)
                                    <span class="mt-0.5 block text-xs font-normal text-neutral-500">{{ $product->sku }}</span>
                                @endif
                            </th>
                            @foreach ($locations as $loc)
                                @php
                                    $stock = $matrix[$product->id][$loc->id] ?? null;
                                    $qty = $stock?->quantity ?? 0;
                                    if ($stock) {
                                        $warn = $stock->isBelowMinimum();
                                    } else {
                                        $warn = $product->minimum_stock !== null && $qty < (int) $product->minimum_stock;
                                    }
                                @endphp
                                <td class="px-3 py-3 text-right tabular-nums @if($warn) bg-red-100 text-red-950 @endif">
                                    <span @class(['font-semibold' => $warn])>{{ $qty }}</span>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $locations->count() + 1 }}" class="px-4 py-8 text-center text-neutral-500">Aucun produit à afficher.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</x-app-layout>
