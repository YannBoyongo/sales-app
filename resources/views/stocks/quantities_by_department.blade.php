<x-app-layout>
    <x-slot name="header">Quantités par département</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Stock</p>
                    <h1 class="app-page-title">Quantités par département</h1>
                    <p class="app-page-desc max-w-3xl">
                        Produits en stock regroupés par catégorie (département), avec la quantité totale sur votre périmètre
                        @if (! $seesAllBranches)
                            (votre branche)
                        @endif
                        . Seuls les produits avec stock &gt; 0 sont affichés.
                    </p>
                </div>
                <a href="{{ route('stocks.index') }}" class="app-btn-secondary shrink-0">Matrice des stocks</a>
            </div>
        </x-slot>

        @if ($departments->isEmpty())
            <div class="app-panel app-panel-body py-12 text-center text-sm text-neutral-500">
                Aucun produit en stock sur votre périmètre.
            </div>
        @else
            <div class="space-y-6">
                @foreach ($departments as $department)
                    <section class="app-panel overflow-hidden">
                        <div class="dashboard-panel-header">
                            <div>
                                <h2 class="font-semibold text-primary-dark">{{ $department['name'] }}</h2>
                                <p class="mt-0.5 text-xs text-neutral-600">
                                    {{ $department['products']->count() }} produit{{ $department['products']->count() > 1 ? 's' : '' }}
                                    · {{ number_format($department['total_quantity'], 0, ',', ' ') }} unités au total
                                </p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="app-table min-w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left">Produit</th>
                                        <th class="px-4 py-3 text-left">Code</th>
                                        <th class="px-4 py-3 text-right">Qté totale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($department['products'] as $product)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $product->product_name }}</td>
                                            <td class="px-4 py-3 font-mono text-xs text-neutral-500">{{ $product->product_sku ?: '—' }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-primary-dark">{{ (int) $product->total_quantity }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>

            <p class="mt-6 text-center text-sm text-neutral-600">
                {{ $productsInStockCount }} produit{{ $productsInStockCount > 1 ? 's' : '' }} en stock
                · {{ $departments->count() }} département{{ $departments->count() > 1 ? 's' : '' }}
            </p>
        @endif
    </x-caisse-flow>
</x-app-layout>
