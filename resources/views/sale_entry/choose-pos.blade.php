<x-app-layout>
    <x-slot name="header">Caisse — Points de vente</x-slot>

    <x-caisse-flow
        max-width="max-w-2xl"
        title="Choisir un point de caisse"
        description="Tous les emplacements auxquels vous avez accès. Les sessions déjà ouvertes sont indiquées par un badge animé."
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
            <div class="space-y-8">
                @foreach ($terminals->groupBy('branch_id')->sortBy(fn ($group) => $group->first()->branch?->name ?? '') as $branchTerminals)
                    @php
                        $branch = $branchTerminals->first()->branch;
                    @endphp
                    <div>
                        @if ($branch)
                            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                {{ $branch->name }}
                            </h2>
                        @endif
                        <div class="space-y-3">
                            @foreach ($branchTerminals->sortBy('name') as $terminal)
                                <a
                                    href="{{ route('pos-terminal.workspace', [$terminal->branch, $terminal]) }}"
                                    class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition-all duration-200 hover:border-primary/30 hover:from-primary/[0.04] hover:to-primary/[0.02] hover:shadow-lg hover:shadow-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 transition group-hover:bg-violet-200/80">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015m-7.5 0a3.004 3.004 0 01-.382-6.019A3.004 3.004 0 006.75 9.75" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-neutral-900">
                                                {{ $terminal->location?->name ?? $terminal->name }}
                                            </p>
                                            @if (isset($openIds[$terminal->id]))
                                                <span class="motion-reduce:animate-none pos-open-badge-blink inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-500/30">
                                                    Ouvert
                                                </span>
                                            @endif
                                        </div>
                                        @if ($terminal->location?->name)
                                            <p class="mt-0.5 text-sm text-neutral-500">
                                                <span class="text-neutral-400">Terminal ·</span> {{ $terminal->name }}
                                            </p>
                                        @else
                                            <p class="mt-0.5 text-sm text-neutral-500">Terminal POS</p>
                                        @endif
                                    </div>
                                    <svg class="h-5 w-5 shrink-0 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-caisse-flow>
</x-app-layout>
