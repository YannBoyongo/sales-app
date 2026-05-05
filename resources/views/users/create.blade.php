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
            <x-input-label for="username" value="Nom d'utilisateur" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username')" required />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
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
        <div>
            <x-input-label for="role" value="Rôle" />
            <select id="role" name="role" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" required>
                @foreach (\App\Enums\UserRole::cases() as $r)
                    <option value="{{ $r->value }}" @selected(old('role', \App\Enums\UserRole::User->value) === $r->value)>{{ $r->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-neutral-500">Les administrateurs et comptables ne sont pas rattachés à une branche. Les utilisateurs boutique et les caissiers (terminal) doivent avoir une branche ; les caissiers sont ensuite affectés à un terminal POS depuis la fiche branche.</p>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>
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
            var roleSelect = document.getElementById('role');
            var branchWrap = document.getElementById('branch-field');
            var branchSelect = document.getElementById('branch_id');
            if (!roleSelect || !branchWrap || !branchSelect) return;
            function sync() {
                var v = roleSelect.value;
                var noBranch = v === 'admin' || v === 'accountant';
                branchWrap.classList.toggle('hidden', noBranch);
                branchSelect.disabled = noBranch;
                branchSelect.required = (v === 'user' || v === 'pos_user');
                if (noBranch) branchSelect.value = '';
            }
            roleSelect.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>
