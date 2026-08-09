<x-app-layout>
    <x-slot name="header">Clients</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="app-page-title">Clients</h1>
            @if (auth()->user()?->canViewClientsLedger())
                <p class="app-page-desc">Liste des clients ayant des ventes à crédit et suivi de leur dette.</p>
            @else
                <p class="app-page-desc">Liste des clients - vos droits permettent de créer ou modifier une fiche (nom et téléphone).</p>
            @endif
        </div>
        @if (auth()->user()?->canEditClientProfile())
            <a href="{{ route('clients.create') }}" class="app-btn-primary">
                Nouveau client
            </a>
        @endif
    </div>

    @if ($showsMultipleBranches)
        <form method="GET" action="{{ route('clients.index') }}" class="app-filter-bar mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="branch_id" class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Branche</label>
                <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Toutes</option>
                    @foreach ($branchesForFilter as $branch)
                        <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="app-btn-primary">Filtrer</button>
                <a href="{{ route('clients.index') }}" class="app-btn-secondary">Réinitialiser</a>
            </div>
        </form>
    @endif

    @php
        $showLedgerCols = auth()->user()?->canViewClientsLedger();
    @endphp

    <section class="app-table-shell">
        <div class="px-6 py-4">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="py-3 pr-4">Client</th>
                        @if ($showsMultipleBranches)
                            <th class="py-3 pr-4">Branche</th>
                        @endif
                        <th class="py-3 pr-4">Téléphone</th>
                        @if ($showLedgerCols)
                            <th class="py-3 pr-4 text-right">Total crédit</th>
                            <th class="py-3 pr-4 text-right">Total payé</th>
                            <th class="py-3 pr-4 text-right">Dette</th>
                        @endif
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
                            @if ($showsMultipleBranches)
                                <td class="py-3 pr-4 text-neutral-700">{{ $client->branch?->name ?? '—' }}</td>
                            @endif
                            <td class="py-3 pr-4 text-neutral-700">{{ $client->phone ?? '-' }}</td>
                            @if ($showLedgerCols)
                                <td class="py-3 pr-4 text-right tabular-nums">{{ \App\Support\Money::usd($totalCredit) }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ \App\Support\Money::usd($totalPaid) }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums text-primary">{{ \App\Support\Money::usd($balance) }}</td>
                            @endif
                            <td class="py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-3">
                                    <a href="{{ route('clients.show', $client) }}" class="text-primary hover:underline">Voir</a>
                                    @if (auth()->user()?->canEditClientProfile())
                                        <a href="{{ route('clients.edit', $client) }}" class="text-neutral-600 hover:underline">Modifier</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($showLedgerCols ? 6 : 3) + ($showsMultipleBranches ? 1 : 0) }}" class="py-8 text-center text-neutral-500">Aucun client pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $clients->links() }}</div>
</x-app-layout>
