<x-app-layout>
    <x-slot name="header">Récapitulatif de clôture — session #{{ $salesSession->id }}</x-slot>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Récapitulatif de clôture</h1>
            <p class="mt-1 text-sm text-neutral-600">
                Session #{{ $salesSession->id }} · {{ $salesSession->branch->name }}
                <span class="ml-2 inline-block rounded bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800">Clôturée</span>
            </p>
            <p class="mt-2 text-xs text-neutral-500">Vue synthèse uniquement — pour la liste des ventes et dépenses détaillées, utilisez le lien ci-dessous.</p>
        </div>
        <div class="flex flex-col items-start gap-2 sm:items-end">
            <a href="{{ route('sales-sessions.index') }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Ventes journalières</a>
            <a href="{{ route('sales-sessions.show', $salesSession) }}" class="text-sm font-medium text-primary underline-offset-2 hover:underline">Détail des ventes &amp; dépenses →</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('success') }}</div>
    @endif

    <div class="space-y-8">
        <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
                <h2 class="text-lg font-semibold text-neutral-900">Données enregistrées à la clôture</h2>
                <p class="mt-1 text-sm text-neutral-600">Montant et justificatif tels qu’enregistrés lors de la validation.</p>
            </div>
            <dl class="grid gap-4 px-6 py-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Date de clôture</dt>
                    <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $salesSession->closed_at?->translatedFormat('d/m/Y à H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Clôturée par</dt>
                    <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $salesSession->closer?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Montant déclaré (USD)</dt>
                    <dd class="mt-1 text-sm font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($salesSession->closure_total_amount ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Justificatif bancaire</dt>
                    <dd class="mt-1 text-sm text-neutral-900">{{ $salesSession->closure_bank_reference ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
                <h2 class="text-lg font-semibold text-neutral-900">Synthèse financière</h2>
                <p class="mt-1 text-sm text-neutral-600">Le total net correspond à Cash − Dépenses. Le crédit est affiché à titre informatif.</p>
            </div>
            <div class="grid gap-4 px-6 py-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-md border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Total cash</p>
                    <p class="mt-1 font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($cashTotal) }}</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Total dépenses</p>
                    <p class="mt-1 font-semibold tabular-nums text-neutral-900">{{ \App\Support\Money::usd($expensesTotal) }}</p>
                </div>
                <div class="rounded-md border border-primary/30 bg-primary/5 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-600">Total général (net)</p>
                    <p class="mt-1 font-semibold tabular-nums text-primary">{{ \App\Support\Money::usd($netTotal) }}</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-white px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Crédit (info)</p>
                    <p class="mt-1 font-semibold tabular-nums text-neutral-700">{{ \App\Support\Money::usd($creditTotal) }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
                <h2 class="text-lg font-semibold text-neutral-900">Ventes par département</h2>
                <p class="mt-1 text-sm text-neutral-600">Nombre d’articles vendus et montant par département (d’après les lignes enregistrées).</p>
            </div>
            <div class="overflow-x-auto px-6 py-4">
                @if ($departmentBreakdown->isEmpty())
                    <p class="py-6 text-center text-sm text-neutral-500">Aucune vente enregistrée sur cette session.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-neutral-200 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                            <tr>
                                <th class="py-3 pr-4">Département</th>
                                <th class="py-3 pr-4 text-right">Articles vendus</th>
                                <th class="py-3 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($departmentBreakdown as $row)
                                <tr>
                                    <td class="py-3 pr-4 font-medium text-neutral-900">{{ $row['name'] }}</td>
                                    <td class="py-3 pr-4 text-right tabular-nums text-neutral-700">{{ $row['quantity'] }}</td>
                                    <td class="py-3 text-right tabular-nums text-neutral-900">{{ \App\Support\Money::usd($row['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-200 text-sm font-semibold">
                            <tr>
                                <td class="py-3 pr-4 text-neutral-900">Total session</td>
                                <td class="py-3 pr-4 text-right tabular-nums text-neutral-600">{{ (int) $salesSession->saleItems->sum('quantity') }}</td>
                                <td class="py-3 text-right tabular-nums text-primary">{{ \App\Support\Money::usd($computedTotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
