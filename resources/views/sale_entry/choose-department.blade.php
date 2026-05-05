<x-app-layout>
    <x-slot name="header">Nouvelle vente — {{ $posTerminal->name }}</x-slot>

    <x-sale-entry-shell
        :step="3"
        title="Quel département ?"
        description="Seuls les produits de ce département (avec stock ou historique sur cette branche) seront proposés à la caisse."
        :contextLine="'<span class=\'text-neutral-500\'>Branche</span> <strong class=\'text-neutral-900\'>' . e($branch->name) . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Terminal</span> <strong class=\'text-neutral-900\'>' . e($posTerminal->name) . '</strong><span class=\'mx-1.5 text-neutral-300\'>·</span><span class=\'text-neutral-500\'>Stock</span> <strong class=\'text-neutral-900\'>' . e($pointOfSale->name) . '</strong>'"
    >
        @if ($departments->isEmpty())
            <div class="flex gap-4 rounded-xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-amber-100/30 p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-200/60 text-amber-900">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="font-semibold text-amber-950">Aucun département disponible</p>
                    <p class="mt-1 text-sm leading-relaxed text-amber-900/90">
                        Ajoutez des stocks ou des ventes liés à la branche, ou associez des produits à un département pour les voir ici.
                    </p>
                </div>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($departments as $d)
                    <a
                        href="{{ route('sales.create', [$branch, $posTerminal, $d]) }}"
                        class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition-all duration-200 hover:border-primary/30 hover:from-primary/[0.04] hover:to-primary/[0.02] hover:shadow-lg hover:shadow-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-800 transition group-hover:bg-teal-200/80">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 6.878V6h12v.878A2.25 2.25 0 0115.75 9h-7.5A2.25 2.25 0 016 6.878zM15.75 15.75v-6h-7.5v6h7.5zM6 12h.75v6H6v-6zM18 12h-.75v6H18v-6zM6.75 3h10.5a.75.75 0 01.75.75v2.356a.75.75 0 01-.207.53L17.25 9H6.75l-1.068-1.364a.75.75 0 01-.207-.53V3.75a.75.75 0 01.75-.75z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-neutral-900">{{ $d->name }}</p>
                            <p class="mt-0.5 text-sm text-neutral-500">Ouvrir la saisie des articles</p>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif

        <x-slot name="footer">
            <a
                href="{{ route('pos-terminal.workspace', [$branch, $posTerminal]) }}"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-primary"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Espace caisse
            </a>
            <a
                href="{{ route('sales.choose-terminal', $branch) }}"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-primary"
            >
                Autre terminal
            </a>
            @if ($canPickAnotherBranch)
                <a
                    href="{{ route('sales.entry') }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-primary"
                >
                    Autre branche
                </a>
            @endif
        </x-slot>
    </x-sale-entry-shell>
</x-app-layout>
