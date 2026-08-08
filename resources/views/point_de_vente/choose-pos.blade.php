<x-app-layout>
    <x-slot name="header">Point de vente</x-slot>

    <x-caisse-flow
        max-width="max-w-2xl"
        title="Choisir un point de vente"
        description="Terminal mobile : le stock est déduit de l’emplacement configuré dans les paramètres. À chaque vente, indiquez la branche et l’emplacement où elle a eu lieu."
    >
        @if ($stockLocation ?? null)
            <p class="mb-4 text-sm text-neutral-600">
                Déstockage : <strong>{{ $stockLocation->name }}</strong>
                @if ($stockLocation->branch)
                    ({{ $stockLocation->branch->name }})
                @endif
            </p>
        @endif

        @if ($terminals->isEmpty())
            <div class="app-alert-warning">
                <p class="font-semibold text-amber-950">Aucun point de vente accessible</p>
                <p class="mt-1 text-sm text-amber-900/90">Un terminal « Point de vente » est créé automatiquement avec chaque branche.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($terminals->sortBy('name') as $terminal)
                    <a
                        href="{{ route('point-de-vente.workspace', [$terminal->branch, $terminal]) }}"
                        class="group flex items-center gap-4 rounded-xl border border-neutral-100 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition hover:border-primary/30 hover:shadow-lg hover:shadow-primary/10"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-neutral-900">{{ $terminal->name }}</p>
                            <p class="mt-0.5 text-sm text-neutral-500">{{ $terminal->branch?->name }}</p>
                        </div>
                        @if (isset($openIds[$terminal->id]))
                            <span class="app-badge-success">Ouvert</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </x-caisse-flow>
</x-app-layout>
