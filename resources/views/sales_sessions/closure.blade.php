<x-app-layout>
    <x-slot name="header">Clôture — session #{{ $salesSession->id }}</x-slot>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-900">Clôture de session #{{ $salesSession->id }}</h1>
            <p class="mt-1 text-sm text-neutral-600">{{ $salesSession->branch->name }} · Récapitulatif avant clôture définitive</p>
        </div>
        <a href="{{ route('sales-sessions.show', $salesSession) }}" class="text-sm text-neutral-600 hover:text-primary underline-offset-2 hover:underline">← Retour aux ventes</a>
    </div>

    @if ($errors->has('close'))
        <div class="mb-4 rounded-md border border-neutral-300 bg-neutral-50 px-4 py-3 text-sm text-neutral-800">{{ $errors->first('close') }}</div>
    @endif

    <div class="space-y-8">
        <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
                <h2 class="text-lg font-semibold text-neutral-900">Synthèse financière</h2>
                <p class="mt-1 text-sm text-neutral-600">Le total net de clôture correspond à Cash - Dépenses. Le crédit est affiché à titre informatif.</p>
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
                    <p class="py-6 text-center text-sm text-neutral-500">Aucune vente enregistrée sur cette session. Vous pouvez tout de même clôturer avec un montant de 0 si besoin.</p>
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

        <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-neutral-900">Justificatif et validation</h2>
            <p class="mt-1 text-sm text-neutral-600">Confirmez le montant total des opérations et le justificatif bancaire.</p>
            <form action="{{ route('sales-sessions.close', $salesSession) }}" method="POST" class="mt-6 max-w-xl space-y-4">
                @csrf
                <div>
                    <x-input-label for="closure_total_amount" value="Montant total à clôturer (USD)" />
                    <x-text-input id="closure_total_amount" name="closure_total_amount" type="number" step="0.01" class="mt-1 block w-full" :value="old('closure_total_amount', $netTotal)" required />
                    <p class="mt-2 text-xs text-neutral-500">Valeur proposée: cash - dépenses.</p>
                    <x-input-error :messages="$errors->get('closure_total_amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="closure_bank_reference" value="Justificatif bancaire" />
                    <x-text-input id="closure_bank_reference" name="closure_bank_reference" type="text" class="mt-1 block w-full" :value="old('closure_bank_reference')" required placeholder="Référence virement, relevé, etc." />
                    <x-input-error :messages="$errors->get('closure_bank_reference')" class="mt-2" />
                </div>
                <div class="flex flex-wrap gap-3 pt-2">
                    <x-primary-button onclick="return confirm('Clôturer définitivement cette session ? Les ventes ne seront plus possibles.');">Valider la clôture</x-primary-button>
                    <a href="{{ route('sales-sessions.show', $salesSession) }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
