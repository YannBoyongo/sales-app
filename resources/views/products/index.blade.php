<x-app-layout>
    <x-slot name="header">Produits</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/90">Catalogue</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Produits</h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-neutral-600">
                        Articles vendables, rattachés à un département. Les seuils servent aux alertes dans la matrice des stocks.
                    </p>
                </div>
                <a
                    href="{{ route('products.create') }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Nouveau produit
                </a>
            </div>
        </x-slot>

        <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Réf.</th>
                        <th class="px-4 py-3">Département</th>
                        <th class="px-4 py-3 text-right">Seuil min.</th>
                        <th class="px-4 py-3 text-right">Prix unitaire</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($products as $product)
                        <tr class="transition-colors hover:bg-neutral-50/80">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $product->sku ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $product->department->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $product->minimum_stock ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($product->unit_price) }}</td>
                            <td class="space-x-2 px-4 py-3 text-right">
                                <a href="{{ route('products.edit', $product) }}" class="text-neutral-700 underline-offset-2 hover:underline">Modifier</a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer ce produit ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-neutral-600 underline-offset-2 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    </x-caisse-flow>
</x-app-layout>
