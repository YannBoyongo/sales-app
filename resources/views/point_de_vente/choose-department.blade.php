<x-app-layout>
    <x-slot name="header">Nouvelle vente - {{ $posTerminal->name }}</x-slot>

    <x-sale-entry-shell
        :step="3"
        :total-steps="4"
        title="Quel département ?"
        description="Choisissez le département des produits à vendre pour cette vente (branche {{ $entryBranch->name }})."
        :contextLine="'<span class=\'text-neutral-500\'>Branche</span> <strong class=\'text-neutral-900\'>' . e($entryBranch->name) . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Vendu à</span> <strong class=\'text-neutral-900\'>' . e($location->name) . '</strong>'"
    >
        @if ($departments->isEmpty())
            <div class="app-alert-warning">
                <p class="font-semibold text-amber-950">Aucun département disponible</p>
                <p class="mt-1 text-sm text-amber-900/90">Aucun produit disponible pour cette branche.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($departments as $department)
                    <a
                        href="{{ route('point-de-vente.sales.create', [$branch, $posTerminal, $entryBranch, $location, $department]) }}"
                        class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition hover:border-primary/30 hover:shadow-lg hover:shadow-primary/10"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-800">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 6.878V6h12v.878A2.25 2.25 0 0115.75 9h-7.5A2.25 2.25 0 016 6.878zM15.75 15.75v-6h-7.5v6h7.5z" /></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-neutral-900">{{ $department->name }}</p>
                            <p class="mt-0.5 text-sm text-neutral-500">Saisir les articles</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <x-slot name="footer">
            <a href="{{ route('point-de-vente.sale.choose-location', [$branch, $posTerminal, $entryBranch]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:text-primary">
                Autre emplacement
            </a>
            <a href="{{ route('point-de-vente.sale.choose-branch', [$branch, $posTerminal]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:text-primary">
                Autre branche
            </a>
        </x-slot>
    </x-sale-entry-shell>
</x-app-layout>
