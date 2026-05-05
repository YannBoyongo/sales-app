<x-app-layout>
    <x-slot name="header">Caisse — {{ $branch->name }}</x-slot>

    <x-sale-entry-shell
        :step="2"
        title="Choisir un terminal"
        description="Chaque terminal est lié à un emplacement de stock. Les ventes déduisent le stock sur cet emplacement."
        :contextLine="'Branche : <strong class=\'text-neutral-900\'>' . e($branch->name) . '</strong>'"
    >
        @if ($terminals->isEmpty())
            <div class="flex gap-4 rounded-xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-amber-100/30 p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-200/60 text-amber-900">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="font-semibold text-amber-950">Aucun terminal accessible</p>
                    <p class="mt-1 text-sm leading-relaxed text-amber-900/90">
                        Les administrateurs peuvent créer des terminaux depuis la fiche branche (section Terminaux POS) et y associer les caissiers.
                    </p>
                </div>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($terminals as $terminal)
                    <a
                        href="{{ route('pos-terminal.workspace', [$branch, $terminal]) }}"
                        class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition-all duration-200 hover:border-primary/30 hover:from-primary/[0.04] hover:to-primary/[0.02] hover:shadow-lg hover:shadow-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 transition group-hover:bg-violet-200/80">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.25M2.25 18.75V5.25m0 13.5h15.75m-15.75 0v.75A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25v-.75m-18 0h18M18 12h.008v.008H18V12zm-3 0h.008v.008H15V12zm3-3h.008v.008H18V9zm-3 0h.008v.008H15V9z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-neutral-900">{{ $terminal->name }}</p>
                                @if (isset($openIds[$terminal->id]))
                                    <span class="motion-reduce:animate-none pos-open-badge-blink inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-500/30">
                                        Ouvert
                                    </span>
                                @endif
                            </div>
                            @if ($terminal->location?->name)
                                <p class="mt-0.5 text-sm text-neutral-500">
                                    <span class="text-neutral-400">Stock ·</span> {{ $terminal->location->name }}
                                </p>
                            @endif
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($canPickAnotherBranch)
            <x-slot name="footer">
                <a
                    href="{{ route('sales.entry') }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-primary"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Autre branche
                </a>
            </x-slot>
        @endif
    </x-sale-entry-shell>
</x-app-layout>
