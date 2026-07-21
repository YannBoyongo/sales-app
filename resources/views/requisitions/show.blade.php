<x-app-layout>
    <x-slot name="header">{{ $requisition->reference }}</x-slot>

    <x-caisse-flow max-width="max-w-7xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Achats</p>
                    <h1 class="app-page-title">{{ $requisition->reference }}</h1>
                    <p class="app-page-desc max-w-2xl">
                        <span class="font-medium text-neutral-800">Date</span> {{ $requisition->date?->format('d/m/Y') ?? '—' }}
                        <span class="text-neutral-300">·</span>
                        <span class="font-medium text-neutral-800">Créé par</span> {{ $requisition->creator?->name ?? '—' }}
                    </p>
                    <div class="mt-4">
                        @if ($requisition->status === \App\Models\Requisition::STATUS_APPROVED)
                            <span class="app-badge-success">{{ $requisition->statusLabel() }}</span>
                        @elseif ($requisition->status === \App\Models\Requisition::STATUS_REJECTED)
                            <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">{{ $requisition->statusLabel() }}</span>
                        @elseif ($requisition->status === \App\Models\Requisition::STATUS_FULFILLED)
                            <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">{{ $requisition->statusLabel() }}</span>
                        @else
                            <span class="app-badge-neutral">{{ $requisition->statusLabel() }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($requisition->status === \App\Models\Requisition::STATUS_OPEN)
                        <a href="{{ route('requisitions.edit', $requisition) }}" class="app-btn-secondary">Modifier</a>
                        <form
                            action="{{ route('requisitions.destroy', $requisition) }}"
                            method="POST"
                            onsubmit="return confirm('Supprimer définitivement cette réquisition ?');"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="app-btn-danger">Supprimer</button>
                        </form>
                    @endif
                    <a href="{{ route('requisitions.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        Retour
                    </a>
                </div>
            </div>
        </x-slot>
    </x-caisse-flow>
</x-app-layout>
