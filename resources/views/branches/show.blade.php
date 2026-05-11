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
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-950 shadow-sm" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="font-medium leading-relaxed">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->has('location'))
            <div class="flex items-start gap-3 rounded-xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-sm text-red-950 shadow-sm" role="alert">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <p class="font-medium leading-relaxed">{{ $errors->first('location') }}</p>
            </div>
        @endif

        @if ($errors->has('terminal'))
            <div class="flex items-start gap-3 rounded-xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-sm text-red-950 shadow-sm" role="alert">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <p class="font-medium leading-relaxed">{{ $errors->first('terminal') }}</p>
            </div>
        @endif

        {{-- En-tête & actions --}}
        <div class="relative overflow-hidden rounded-2xl border border-neutral-200/90 bg-white shadow-sm ring-1 ring-black/[0.03]">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-blue-500 to-sky-400" aria-hidden="true"></div>
            <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-start sm:justify-between sm:p-8">
                <div class="min-w-0 space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary/90">Fiche branche</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">{{ $branch->name }}</h1>
                    <p class="max-w-xl pt-1 text-sm leading-relaxed text-neutral-600">
                        Gérez les emplacements stock et les terminaux de caisse rattachés à cette branche.
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1 rounded-xl border border-neutral-200/90 bg-neutral-50/80 p-1 shadow-inner">
                    <a
                        href="{{ route('branches.edit', $branch) }}"
                        class="inline-flex rounded-lg p-2.5 text-neutral-600 transition hover:bg-white hover:text-neutral-900 hover:shadow-sm"
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
                            class="inline-flex rounded-lg p-2.5 text-red-600 transition hover:bg-white hover:text-red-700 hover:shadow-sm"
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

            {{-- Statistiques --}}
            <div class="grid gap-px border-t border-neutral-200/80 bg-neutral-200/80 sm:grid-cols-3">
                <div class="flex items-center gap-4 bg-white px-6 py-4 sm:px-8">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums text-neutral-900">{{ $locationTotal }}</p>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Emplacements</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 bg-white px-6 py-4 sm:px-8">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.25M2.25 18.75V5.25m0 13.5h15.75m-15.75 0v.75A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25v-.75m-18 0h18" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums text-neutral-900">{{ $terminalTotal }}</p>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Terminaux POS</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 bg-white px-6 py-4 sm:px-8">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tabular-nums text-neutral-900">#{{ $branch->id }}</p>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Réf. interne</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            {{-- Emplacements --}}
            <section class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white shadow-sm ring-1 ring-black/[0.03]">
                <div class="flex flex-col gap-4 border-b border-neutral-100 bg-gradient-to-b from-neutral-50/90 to-white px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">Emplacements</h2>
                        <p class="mt-0.5 text-sm text-neutral-600">Entrepôts et points de vente — le stock est suivi par emplacement.</p>
                    </div>
                    <a
                        href="{{ route('branches.locations.create', $branch) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nouvel emplacement
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 bg-neutral-50/80 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <th class="whitespace-nowrap px-6 py-3.5">Nom</th>
                                <th class="whitespace-nowrap px-6 py-3.5">Type</th>
                                <th class="whitespace-nowrap px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($locations as $location)
                                @php
                                    $kindBadge = match ($location->kind) {
                                        \App\Models\Location::KIND_MAIN => ['class' => 'bg-sky-50 text-sky-800 ring-sky-200/60', 'dot' => 'bg-sky-500'],
                                        \App\Models\Location::KIND_POINT_OF_SALE => ['class' => 'bg-emerald-50 text-emerald-800 ring-emerald-200/60', 'dot' => 'bg-emerald-500'],
                                        default => ['class' => 'bg-neutral-100 text-neutral-800 ring-neutral-200/60', 'dot' => 'bg-neutral-400'],
                                    };
                                @endphp
                                <tr class="transition-colors hover:bg-neutral-50/70">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-neutral-900">{{ $location->name }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $kindBadge['class'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $kindBadge['dot'] }}" aria-hidden="true"></span>
                                            {{ \App\Models\Location::kindLabel($location->kind) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="inline-flex items-center justify-end gap-0.5">
                                            <a
                                                href="{{ route('branches.locations.edit', [$branch, $location]) }}"
                                                class="inline-flex rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
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
                                                    class="inline-flex rounded-lg p-2 text-red-500 transition hover:bg-red-50 hover:text-red-700"
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
                                    <td colspan="3" class="px-6 py-14 text-center">
                                        <div class="mx-auto max-w-sm">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4" />
                                                </svg>
                                            </div>
                                            <p class="mt-4 text-sm font-medium text-neutral-900">Aucun emplacement</p>
                                            <p class="mt-1 text-sm text-neutral-600">Ajoutez un entrepôt principal, un secondaire ou un point de vente.</p>
                                            <a href="{{ route('branches.locations.create', $branch) }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-95">Créer un emplacement</a>
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
            <section class="overflow-hidden rounded-2xl border border-neutral-200/90 bg-white shadow-sm ring-1 ring-black/[0.03]">
                <div class="flex flex-col gap-4 border-b border-neutral-100 bg-gradient-to-b from-neutral-50/90 to-white px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">Terminaux POS</h2>
                        <p class="mt-0.5 text-sm text-neutral-600">Caisse et sessions — chaque terminal est lié à un point de vente.</p>
                    </div>
                    <a
                        href="{{ route('branches.pos-terminals.create', $branch) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nouveau terminal
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 bg-neutral-50/80 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                <th class="whitespace-nowrap px-6 py-3.5">Nom</th>
                                <th class="whitespace-nowrap px-6 py-3.5">Emplacement</th>
                                <th class="whitespace-nowrap px-6 py-3.5">Caissiers</th>
                                <th class="whitespace-nowrap px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($terminals as $t)
                                <tr class="transition-colors hover:bg-neutral-50/70">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-neutral-900">{{ $t->name }}</td>
                                    <td class="px-6 py-4 text-neutral-600">{{ $t->location?->name ?? '—' }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex min-w-[2rem] items-center justify-center rounded-lg bg-neutral-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-neutral-800">{{ $t->pos_users_count }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="inline-flex items-center justify-end gap-0.5">
                                            <a
                                                href="{{ route('branches.pos-terminals.edit', [$branch, $t]) }}"
                                                class="inline-flex rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
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
                                                    class="inline-flex rounded-lg p-2 text-red-500 transition hover:bg-red-50 hover:text-red-700"
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
                                            <a href="{{ route('branches.pos-terminals.create', $branch) }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-95">Créer un terminal</a>
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
