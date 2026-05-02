<x-app-layout>
    <x-slot name="header">Clients</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Clients</h1>
            <p class="mt-1 text-sm text-neutral-600">Liste des clients ayant des ventes à crédit et suivi de leur dette.</p>
        </div>
        <a href="{{ route('clients.create') }}" class="inline-flex items-center justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            Nouveau client
        </a>
    </div>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto px-6 py-4">
            <table class="min-w-full text-sm">
                <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                    <tr>
                        <th class="py-3 pr-4">Client</th>
                        <th class="py-3 pr-4">Téléphone</th>
                        <th class="py-3 pr-4 text-right">Total crédit</th>
                        <th class="py-3 pr-4 text-right">Total payé</th>
                        <th class="py-3 pr-4 text-right">Dette</th>
                        <th class="py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($clients as $client)
                        @php
                            $totalCredit = (float) ($client->total_credit_amount ?? 0);
                            $totalPaid = (float) ($client->payments_sum_amount ?? 0);
                            $balance = $totalCredit - $totalPaid;
                        @endphp
                        <tr>
                            <td class="py-3 pr-4 font-medium text-neutral-900">{{ $client->name }}</td>
                            <td class="py-3 pr-4 text-neutral-700">{{ $client->phone ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums">{{ \App\Support\Money::usd($totalCredit) }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums">{{ \App\Support\Money::usd($totalPaid) }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums text-primary">{{ \App\Support\Money::usd($balance) }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('clients.show', $client) }}" class="text-primary hover:underline">Voir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-neutral-500">Aucun client pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $clients->links() }}</div>
</x-app-layout>
