<x-app-layout>
    <x-slot name="header">Ventes journalières</x-slot>

    <x-page-header title="Ventes journalières" action="Nouvelle vente" :action-href="route('sales-sessions.create')" />

    <div class="overflow-x-auto overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600">
                <tr>
                    <th class="px-3 py-3 whitespace-nowrap">Ouverture</th>
                    <th class="px-3 py-3 whitespace-nowrap">Branche</th>
                    <th class="px-3 py-3 whitespace-nowrap">Utilisateur</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Total ventes</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Dépenses</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Net cash</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Crédit</th>
                    <th class="px-3 py-3 whitespace-nowrap min-w-[8rem]">Réf. bancaire</th>
                    <th class="px-3 py-3 whitespace-nowrap">Statut</th>
                    <th class="px-3 py-3 text-right whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($sessions as $session)
                    @php
                        $cash = (string) ($session->session_cash_total ?? '0');
                        $exp = (string) ($session->session_expenses_total ?? '0');
                        $credit = (string) ($session->session_credit_total ?? '0');
                        $netCash = bcsub($cash, $exp, 2);
                    @endphp
                    <tr class="hover:bg-neutral-50/80">
                        <td class="px-3 py-3 text-neutral-600 whitespace-nowrap">{{ $session->opened_at->translatedFormat('d/m/Y H:i') }}</td>
                        <td class="px-3 py-3 font-medium text-neutral-900">{{ $session->branch->name }}</td>
                        <td class="px-3 py-3 text-neutral-700">{{ $session->opener?->name ?? '—' }}</td>
                        <td class="px-3 py-3 text-right tabular-nums font-medium text-neutral-900">{{ \App\Support\Money::usd($session->sale_items_sum_line_total ?? 0) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-neutral-800">{{ \App\Support\Money::usd($exp) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums font-medium text-primary">{{ \App\Support\Money::usd($netCash) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800">{{ \App\Support\Money::usd($credit) }}</td>
                        <td class="px-3 py-3 text-xs text-neutral-700 max-w-[10rem] truncate" title="{{ $session->closure_bank_reference ?? '' }}">{{ $session->closure_bank_reference ?? '—' }}</td>
                        <td class="px-3 py-3">
                            @if ($session->isOpen())
                                <span class="rounded bg-primary px-2 py-0.5 text-xs font-medium text-white">Ouverte</span>
                            @else
                                <span class="rounded bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800">Clôturée</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($session->isOpen())
                                    <a
                                        href="{{ route('sales-sessions.show', $session) }}"
                                        class="inline-flex items-center rounded-md p-1 text-primary hover:bg-primary/10"
                                        aria-label="Ouvrir la session"
                                        title="Continuer la session"
                                    >
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M8 5v14l11-7L8 5z" />
                                        </svg>
                                    </a>
                                @else
                                    <a
                                        href="{{ route('sales-sessions.closure-recap', $session) }}"
                                        class="inline-flex items-center rounded-md p-1 text-neutral-700 hover:bg-neutral-100 hover:text-primary"
                                        aria-label="Voir la clôture"
                                        title="Récapitulatif de clôture"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15.75A3.75 3.75 0 1012 8.25a3.75 3.75 0 000 7.5z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('sales-sessions.show', $session) }}"
                                        class="inline-flex items-center rounded-md p-1 text-neutral-700 hover:bg-neutral-100 hover:text-primary"
                                        aria-label="Détail de la session"
                                        title="Détail session (ventes, lignes…)"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                        </svg>
                                    </a>
                                @endif
                                @if (auth()->user()->is_admin && ! $session->isOpen())
                                    <form action="{{ route('sales-sessions.reopen', $session) }}" method="POST">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                                            onclick="return confirm('Rouvrir cette session ? Les informations de clôture seront effacées.')"
                                        >
                                            Rouvrir
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sessions->links() }}</div>
</x-app-layout>
