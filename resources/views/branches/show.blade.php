@php
    $locationTotal = $locations->total();
    $terminalTotal = $terminals->count();
@endphp

<x-app-layout>
    <x-slot name="header">{{ $branch->name }}</x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        {{-- Breadcrumb --}}
        <nav class="flex flex-wrap items-center gap-2 text-sm text-neutral-500" aria-label="Fil d’Ariane">
            <a href="{{ route('branches.index') }}" class="font-medium text-neutral-600 transition hover:text-primary">Branches</a>
            <span class="text-neutral-300" aria-hidden="true">/</span>
            <span class="font-medium text-neutral-900">{{ $branch->name }}</span>
        </nav>

        @if (session('success'))
            <div class="app-alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->has('location'))
            <div class="app-alert-danger" role="alert">{{ $errors->first('location') }}</div>
        @endif

        @if ($errors->has('terminal'))
            <div class="app-alert-danger" role="alert">{{ $errors->first('terminal') }}</div>
        @endif

        {{-- En-tête & actions --}}
        <div class="app-panel">
            <div class="app-panel-body flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="app-page-eyebrow">Fiche branche</p>
                    <h1 class="app-page-title">{{ $branch->name }}</h1>
                    <p class="app-page-desc max-w-xl">
                        Gérez les emplacements stock et les terminaux de caisse rattachés à cette branche.
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <a
                        href="{{ route('branches.edit', $branch) }}"
                        class="app-icon-btn"
                        title="Modifier la branche"
                    >
                        <span class="sr-only">Modifier</span>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                    <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="inline-flex" onsubmit="return confirm('Supprimer cette branche ? Les emplacements doivent être supprimés au préalable.');">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="app-icon-btn text-red-600 hover:bg-red-50 hover:text-red-700"
                            title="Supprimer la branche"
                        >
                            <span class="sr-only">Supprimer</span>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-100 px-4 pb-5 pt-0 sm:grid-cols-3 sm:px-5 lg:px-6">
                <div class="app-stat-card">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Emplacements</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-neutral-900">{{ $locationTotal }}</p>
                </div>
                <div class="app-stat-card">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Terminaux POS</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-neutral-900">{{ $terminalTotal }}</p>
                </div>
                <div class="app-stat-card">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Réf. interne</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-neutral-900">#{{ $branch->id }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            {{-- Emplacements --}}
            <section class="app-panel">
                <div class="app-panel-header">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">Emplacements</h2>
                        <p class="mt-0.5 text-sm text-neutral-600">Entrepôts et points de vente — le stock est suivi par emplacement.</p>
                    </div>
                    <a
                        href="{{ route('branches.locations.create', $branch) }}"
                        class="app-btn-primary shrink-0"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nouvel emplacement
                    </a>
                </div>
                <div class="app-table-shell border-0 shadow-none">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">Nom</th>
                                <th class="whitespace-nowrap">Type</th>
                                <th>Magasiniers</th>
                                <th class="whitespace-nowrap text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($locations as $location)
                                @php
                                    $kindBadgeClass = match ($location->kind) {
                                        \App\Models\Location::KIND_MAIN => 'app-badge-info',
                                        \App\Models\Location::KIND_POINT_OF_SALE => 'app-badge-success',
                                        default => 'app-badge-neutral',
                                    };
                                @endphp
                                <tr>
                                    <td class="font-medium text-neutral-900">{{ $location->name }}</td>
                                    <td>
                                        <span class="{{ $kindBadgeClass }}">{{ \App\Models\Location::kindLabel($location->kind) }}</span>
                                    </td>
                                    <td class="max-w-xs text-neutral-600">
                                        @if ($location->stockManagers->isEmpty())
                                            <span class="text-neutral-400">—</span>
                                        @else
                                            <span class="leading-snug">{{ $location->stockManagers->pluck('name')->join(', ') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="inline-flex items-center justify-end gap-0.5">
                                            <a
                                                href="{{ route('branches.locations.edit', [$branch, $location]) }}"
                                                class="app-icon-btn"
                                                title="Modifier l’emplacement"
                                            >
                                                <span class="sr-only">Modifier</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('branches.locations.destroy', [$branch, $location]) }}" method="POST" class="inline-flex" onsubmit="return confirm('Supprimer cet emplacement ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="app-icon-btn text-red-600 hover:bg-red-50 hover:text-red-700"
                                                    title="Supprimer l’emplacement"
                                                >
                                                    <span class="sr-only">Supprimer</span>
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-14 text-center">
                                        <div class="mx-auto max-w-sm">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4" />
                                                </svg>
                                            </div>
                                            <p class="mt-4 text-sm font-medium text-neutral-900">Aucun emplacement</p>
                                            <p class="mt-1 text-sm text-neutral-600">Ajoutez un entrepôt principal, un secondaire ou un point de vente.</p>
                                            <a href="{{ route('branches.locations.create', $branch) }}" class="app-btn-primary mt-4">Créer un emplacement</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($locations->hasPages())
                    <div class="border-t border-neutral-100 bg-neutral-50/30 px-6 py-4">{{ $locations->links() }}</div>
                @endif
            </section>

            {{-- Terminaux POS --}}
            <section class="app-panel">
                <div class="app-panel-header">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">Terminaux POS</h2>
                        <p class="mt-0.5 text-sm text-neutral-600">Caisse et sessions — chaque terminal est lié à un point de vente.</p>
                    </div>
                    <a
                        href="{{ route('branches.pos-terminals.create', $branch) }}"
                        class="app-btn-primary shrink-0"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nouveau terminal
                    </a>
                </div>
                <div class="app-table-shell border-0 shadow-none">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">Nom</th>
                                <th class="whitespace-nowrap">Emplacement</th>
                                <th class="whitespace-nowrap">Caissiers</th>
                                <th class="whitespace-nowrap text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($terminals as $t)
                                <tr>
                                    <td class="font-medium text-neutral-900">{{ $t->name }}</td>
                                    <td>{{ $t->location?->name ?? '—' }}</td>
                                    <td>
                                        <span class="app-badge-neutral tabular-nums">{{ $t->pos_users_count }}</span>
                                    </td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="inline-flex items-center justify-end gap-0.5">
                                            <a
                                                href="{{ route('branches.pos-terminals.edit', [$branch, $t]) }}"
                                                class="app-icon-btn"
                                                title="Modifier le terminal"
                                            >
                                                <span class="sr-only">Modifier</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('branches.pos-terminals.destroy', [$branch, $t]) }}" method="POST" class="inline-flex" onsubmit="return confirm('Supprimer ce terminal ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="app-icon-btn text-red-600 hover:bg-red-50 hover:text-red-700"
                                                    title="Supprimer le terminal"
                                                >
                                                    <span class="sr-only">Supprimer</span>
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-14 text-center">
                                        <div class="mx-auto max-w-sm">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.25M2.25 18.75V5.25m0 13.5h15.75" />
                                                </svg>
                                            </div>
                                            <p class="mt-4 text-sm font-medium text-neutral-900">Aucun terminal POS</p>
                                            <p class="mt-1 text-sm text-neutral-600">Créez un terminal et associez-le à un emplacement « Point de vente ».</p>
                                            <a href="{{ route('branches.pos-terminals.create', $branch) }}" class="app-btn-primary mt-4">Créer un terminal</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="flex justify-center border-t border-neutral-200/80 pt-8">
            <a
                href="{{ route('branches.index') }}"
                class="inline-flex items-center gap-2 text-sm font-medium text-neutral-600 transition hover:text-primary"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Retour à la liste des branches
            </a>
        </div>
    </div>
</x-app-layout>
