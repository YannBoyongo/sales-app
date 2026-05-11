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
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <a
                        href="{{ route('products.export.pdf') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-800 shadow-sm transition hover:bg-neutral-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        title="Exporter en PDF"
                    >
                        <svg class="h-4 w-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        PDF
                    </a>
                    <a
                        href="{{ route('products.export.excel') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-800 shadow-sm transition hover:bg-neutral-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        title="Exporter en Excel (.xlsx)"
                    >
                        <svg class="h-4 w-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Excel
                    </a>
                    @unless (auth()->user()?->isInventoryReadOnly())
                        <a
                            href="{{ route('products.create') }}"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        >
                            Nouveau produit
                        </a>
                    @endunless
                </div>
            </div>
        </x-slot>

        @if (session('import_errors'))
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="alert">
                <p class="font-semibold">Import : détails</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach (session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($errors->has('file'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-950" role="alert">{{ $errors->first('file') }}</div>
        @endif

        @unless (auth()->user()?->isInventoryReadOnly())
            <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-neutral-200/90 bg-white/90 p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Importer des produits</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Fichier <strong>Excel (.xlsx)</strong> ou <strong>CSV</strong> : lecture dans le navigateur, puis envoi au serveur (UTF-8 ; ou , pour le CSV).
                    </p>
                    <a
                        href="{{ route('products.import.sample') }}"
                        class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Télécharger le modèle (exemple)
                    </a>
                </div>
                <form
                    id="product-import-form"
                    action="{{ route('products.import.json') }}"
                    method="POST"
                    class="flex flex-wrap items-end gap-2"
                    data-import-url="{{ route('products.import.json') }}"
                >
                    @csrf
                    <div>
                        <label for="product-import-file" class="sr-only">Fichier import</label>
                        <input
                            id="product-import-file"
                            type="file"
                            accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain"
                            required
                            class="block w-full max-w-xs text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white"
                        />
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-neutral-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-neutral-900">
                        Importer
                    </button>
                </form>
            </div>
        @endunless

        <div class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/90 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-900/5 backdrop-blur-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50/90 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Département</th>
                        <th class="px-4 py-3 text-right">Seuil min.</th>
                        <th class="px-4 py-3 text-right">Prix unitaire</th>
                        @unless (auth()->user()?->isInventoryReadOnly())
                            <th class="px-4 py-3 text-right">Actions</th>
                        @endunless
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
                            @unless (auth()->user()?->isInventoryReadOnly())
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a
                                            href="{{ route('products.edit', $product) }}"
                                            title="Modifier"
                                            aria-label="Modifier"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 112.97 2.97L9.75 16.54 6 17.25l.71-3.75 10.152-10.013z" />
                                            </svg>
                                        </a>
                                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer ce produit ?');">
                                        @csrf
                                        @method('DELETE')
                                            <button
                                                type="submit"
                                                title="Supprimer"
                                                aria-label="Supprimer"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7.5h12m-9.75 0V6a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v1.5m-8.25 0v10.5A1.5 1.5 0 009 19.5h6a1.5 1.5 0 001.5-1.5V7.5M10.5 10.5v6m3-6v6" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    </x-caisse-flow>
</x-app-layout>
