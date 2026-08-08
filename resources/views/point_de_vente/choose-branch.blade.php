<x-app-layout>
    <x-slot name="header">Nouvelle vente - {{ $posTerminal->name }}</x-slot>

    <x-sale-entry-shell
        :step="1"
        :total-steps="4"
        title="Quelle branche ?"
        description="Indiquez la branche où la vente a eu lieu. Cette information sert uniquement à l'enregistrement de la vente."
    >
        <div class="space-y-3">
            @foreach ($branches as $entryBranch)
                <a
                    href="{{ route('point-de-vente.sale.choose-location', [$branch, $posTerminal, $entryBranch]) }}"
                    class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition hover:border-primary/30 hover:shadow-lg hover:shadow-primary/10"
                >
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008z" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-neutral-900">{{ $entryBranch->name }}</p>
                        <p class="mt-0.5 text-sm text-neutral-500">Choisir l’emplacement de vente</p>
                    </div>
                </a>
            @endforeach
        </div>

        <x-slot name="footer">
            <a href="{{ route('point-de-vente.workspace', [$branch, $posTerminal]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:text-primary">
                Retour caisse
            </a>
        </x-slot>
    </x-sale-entry-shell>
</x-app-layout>
