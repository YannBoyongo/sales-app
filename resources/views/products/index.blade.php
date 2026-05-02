<x-app-layout>
    <x-slot name="header">Produits</x-slot>

    <x-page-header title="Produits" action="Nouveau produit" :action-href="route('products.create')" />

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
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
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $product->sku ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $product->department->name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $product->minimum_stock ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::usd($product->unit_price) }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
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
</x-app-layout>
