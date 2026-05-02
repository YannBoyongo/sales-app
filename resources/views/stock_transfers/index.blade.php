<x-app-layout>
    <x-slot name="header">Transferts de stock</x-slot>

    <x-page-header
        title="Transferts de stock"
        action="Nouveau transfert"
        :action-href="route('stock-transfers.create')"
    />

    @if (session('success'))
        <div class="mb-4 rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ session('success') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-4 py-3">Réf.</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">De</th>
                    <th class="px-4 py-3">Vers</th>
                    <th class="px-4 py-3 text-right">Lignes</th>
                    <th class="px-4 py-3">Par</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($transfers as $t)
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-4 py-3 font-medium text-neutral-900 tabular-nums">#{{ $t->id }}</td>
                        <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">{{ $t->transferred_at->translatedFormat('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-neutral-700">{{ $t->fromLocation->name }} <span class="text-neutral-400">({{ $t->fromLocation->branch->name }})</span></td>
                        <td class="px-4 py-3 text-neutral-700">{{ $t->toLocation->name }} <span class="text-neutral-400">({{ $t->toLocation->branch->name }})</span></td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $t->items_count }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $t->user->name }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('stock-transfers.show', $t) }}" class="text-neutral-700 underline-offset-2 hover:underline">Détail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-500">Aucun transfert pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transfers->links() }}</div>
</x-app-layout>
