<x-app-layout>
    <x-slot name="header">Nouvelle vente - {{ $posTerminal->name }}</x-slot>

    <x-sale-entry-shell
        :step="2"
        :total-steps="4"
        title="Quel emplacement ?"
        description="Indiquez l'emplacement où la vente a eu lieu (saisie uniquement, sans impact sur le stock)."
        :contextLine="'<span class=\'text-neutral-500\'>Branche</span> <strong class=\'text-neutral-900\'>' . e($entryBranch->name) . '</strong>'"
    >
        @if ($saleLocations->isEmpty())
            <div class="app-alert-warning">
                <p class="font-semibold text-amber-950">Aucun emplacement dans cette branche</p>
                <p class="mt-1 text-sm text-amber-900/90">Créez un emplacement pour cette branche avant d'enregistrer une vente.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($saleLocations as $saleLocation)
                    <a
                        href="{{ route('point-de-vente.sale.choose-department', [$branch, $posTerminal, $entryBranch, $saleLocation]) }}"
                        class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition hover:border-primary/30 hover:shadow-lg hover:shadow-primary/10"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-neutral-900">{{ $saleLocation->name }}</p>
                            <p class="mt-0.5 text-sm text-neutral-500">{{ \App\Models\Location::kindLabel($saleLocation->kind) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <x-slot name="footer">
            <a href="{{ route('point-de-vente.sale.choose-branch', [$branch, $posTerminal]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:text-primary">
                Autre branche
            </a>
            <a href="{{ route('point-de-vente.workspace', [$branch, $posTerminal]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:text-primary">
                Retour caisse
            </a>
        </x-slot>
    </x-sale-entry-shell>
</x-app-layout>
