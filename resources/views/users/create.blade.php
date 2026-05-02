<x-app-layout>
    <x-slot name="header">Nouvel utilisateur</x-slot>

    <x-page-header title="Nouvel utilisateur" />

    <form action="{{ route('users.store') }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <x-input-label for="name" value="Nom" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirmation du mot de passe" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>
        <div class="flex items-center gap-2">
            <input id="is_admin" name="is_admin" type="checkbox" value="1" class="rounded border-neutral-300 text-primary shadow-sm focus:ring-primary" @checked(old('is_admin'))>
            <x-input-label for="is_admin" value="Administrateur (gestion des utilisateurs)" class="!mb-0" />
        </div>
        <p class="text-xs text-neutral-500">Les administrateurs voient toutes les branches et ne sont rattachés à aucune branche.</p>
        <x-input-error :messages="$errors->get('is_admin')" class="mt-2" />
        <div id="branch-field" class="space-y-1">
            <x-input-label for="branch_id" value="Branche" />
            <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                <option value="">— Choisir une branche —</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
        </div>
        <div class="flex gap-3">
            <x-primary-button>Créer</x-primary-button>
            <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
    <script>
        (function () {
            var admin = document.getElementById('is_admin');
            var branchWrap = document.getElementById('branch-field');
            var branchSelect = document.getElementById('branch_id');
            if (!admin || !branchWrap || !branchSelect) return;
            function sync() {
                var on = admin.checked;
                branchWrap.classList.toggle('hidden', on);
                branchSelect.disabled = on;
                if (on) branchSelect.value = '';
            }
            admin.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>
