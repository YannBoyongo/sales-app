<x-app-layout>
    <x-slot name="header">Nouvelle réquisition</x-slot>

    <x-caisse-flow max-width="max-w-3xl" :with-card="false">
        <x-slot name="header">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="app-page-eyebrow">Achats</p>
                    <h1 class="app-page-title">Nouvelle réquisition</h1>
                    <p class="app-page-desc max-w-2xl">
                        La référence sera générée automatiquement à l’enregistrement.
                    </p>
                </div>
                <a href="{{ route('requisitions.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-white/80 hover:text-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    Retour
                </a>
            </div>
        </x-slot>

        <form action="{{ route('requisitions.store') }}" method="POST" class="app-panel">
            @csrf
            <div class="app-panel-body space-y-4">
                <div>
                    <label for="date" class="block text-xs font-semibold text-neutral-700">Date</label>
                    <input
                        id="date"
                        name="date"
                        type="date"
                        value="{{ old('date', now()->toDateString()) }}"
                        required
                        class="mt-1 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>

                <p class="text-sm text-neutral-500">
                    Référence : <span class="font-medium text-neutral-700">générée automatiquement</span>
                    (ex. REQ-A7K29041)
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                <a href="{{ route('requisitions.index') }}" class="app-btn-secondary text-center">Annuler</a>
                <button type="submit" class="app-btn-primary">Enregistrer</button>
            </div>
        </form>
    </x-caisse-flow>
</x-app-layout>
