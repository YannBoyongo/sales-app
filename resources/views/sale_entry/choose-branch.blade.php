<x-app-layout>
    <x-slot name="header">Nouvelle vente</x-slot>

    <x-sale-entry-shell
        :step="1"
        title="Quelle branche ?"
        :description="auth()->user()?->canBypassBranchScope()
            ? 'Choisissez la boutique pour laquelle vous ouvrez la caisse. Ensuite, vous sélectionnerez le terminal POS.'
            : 'Sélectionnez la branche sur laquelle cette vente sera enregistrée.'"
    >
        <div class="space-y-3">
            @foreach ($branches as $b)
                <a
                    href="{{ route('sales.choose-terminal', $b) }}"
                    class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition-all duration-200 hover:border-primary/30 hover:from-primary/[0.04] hover:to-primary/[0.02] hover:shadow-lg hover:shadow-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:bg-primary/15">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.25 21h19.5m-18-18v18m2.25-18v18m13.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.108c.621 0 1.125.504 1.125 1.125v8.25m-15-12h.108c.621 0 1.125.504 1.125 1.125v8.25" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-neutral-900">{{ $b->name }}</p>
                        <p class="mt-0.5 text-sm text-neutral-500">Voir les terminaux POS de cette branche</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endforeach
        </div>
    </x-sale-entry-shell>
</x-app-layout>
