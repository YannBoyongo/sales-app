<x-branch-picker-layout>
    <div>
        <h1 class="text-2xl font-semibold text-neutral-900">Branche de travail</h1>
        <p class="mt-2 text-sm text-neutral-600">
            Sélectionnez la branche dont vous consultez et gérez les données. Vous pourrez la changer à tout moment depuis l’en-tête.
        </p>

        <x-input-error :messages="$errors->get('branch_id')" class="mt-4" />

        <ul class="mt-6 space-y-3">
            @foreach ($branches as $branch)
                <li>
                    <form action="{{ route('active-branch.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}" />
                        <div class="group flex flex-col gap-3 rounded-xl border border-neutral-200 bg-gradient-to-br from-white to-neutral-50/80 p-4 transition hover:border-primary/40 hover:shadow-md sm:flex-row sm:items-center sm:gap-4 @if ((string) old('branch_id', \App\Support\ActiveBranch::id()) === (string) $branch->id) border-primary ring-2 ring-primary/20 @endif">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition group-hover:bg-primary/15">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.25 21h19.5m-18-18v18m2.25-18v18m13.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.108c.621 0 1.125.504 1.125 1.125v8.25m-15-12h.108c.621 0 1.125.504 1.125 1.125v8.25" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-neutral-900">{{ $branch->name }}</p>
                                @if ((string) old('branch_id', \App\Support\ActiveBranch::id()) === (string) $branch->id)
                                    <p class="mt-0.5 text-xs font-medium text-primary">Branche actuelle</p>
                                @endif
                            </div>
                            <button
                                type="submit"
                                class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:w-auto"
                            >
                                Continuer
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-branch-picker-layout>
