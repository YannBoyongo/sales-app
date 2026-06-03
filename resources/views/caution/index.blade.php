<x-app-layout>
    <x-slot name="header">Caution</x-slot>

    <div class="mb-6">
        <h1 class="app-page-title">Caution clients</h1>
        <p class="app-page-desc">Clients ayant versé une caution — total déposé, montant utilisé et solde disponible.</p>
    </div>

    <section class="app-table-shell">
        <div class="px-6 py-4">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="py-3 pr-4">Client</th>
                        <th class="py-3 pr-4">Téléphone</th>
                        <th class="py-3 pr-4 text-right">Total caution</th>
                        <th class="py-3 pr-4 text-right">Montant utilisé</th>
                        <th class="py-3 pr-4 text-right">Solde</th>
                        <th class="py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($clients as $client)
                        @php
                            $total = (string) ($client->caution_total ?? '0');
                            $used = (string) ($client->caution_used ?? '0');
                            $balance = bcsub($total, $used, 2);
                        @endphp
                        <tr class="hover:bg-neutral-50/50">
                            <td class="py-3 pr-4 font-medium text-neutral-900">{{ $client->name }}</td>
                            <td class="py-3 pr-4 text-neutral-700">{{ $client->phone ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums">{{ \App\Support\Money::usd($total) }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums text-neutral-700">{{ \App\Support\Money::usd($used) }}</td>
                            <td class="py-3 pr-4 text-right tabular-nums font-semibold text-sky-900">{{ \App\Support\Money::usd($balance) }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('clients.show', $client) }}" class="text-primary hover:underline">Voir la fiche</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-neutral-500">Aucun client avec une caution enregistrée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $clients->links() }}</div>
</x-app-layout>
