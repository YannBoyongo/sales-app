<x-app-layout>
    <x-slot name="header">Modifier la branche</x-slot>

    <x-page-header title="Modifier la branche" />

    <form action="{{ route('branches.update', $branch) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="name" value="Nom" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $branch->name)" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        @if (auth()->user()?->isSuperAdmin())
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        type="checkbox"
                        name="can_sell_on_credit"
                        value="1"
                        class="mt-1 rounded border-neutral-300 text-primary focus:ring-primary"
                        @checked(old('can_sell_on_credit', $branch->can_sell_on_credit))
                    />
                    <span>
                        <span class="block text-sm font-semibold text-neutral-900">Ventes à crédit (revendeur/client)</span>
                        <span class="mt-0.5 block text-sm text-neutral-600">Active l’onglet Revendeur/Client sur le terminal POS de cette branche (crédit, caution).</span>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('can_sell_on_credit')" class="mt-2" />
            </div>
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        type="checkbox"
                        name="can_apply_discount"
                        value="1"
                        class="mt-1 rounded border-neutral-300 text-primary focus:ring-primary"
                        @checked(old('can_apply_discount', $branch->can_apply_discount))
                    />
                    <span>
                        <span class="block text-sm font-semibold text-neutral-900">Remise sur vente</span>
                        <span class="mt-0.5 block text-sm text-neutral-600">Active la case « Remise » sur le terminal POS pour modifier les prix unitaires.</span>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('can_apply_discount')" class="mt-2" />
            </div>
        @endif
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('branches.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
</x-app-layout>
