<x-app-layout>
    <x-slot name="header">Modifier l’utilisateur</x-slot>

    <x-page-header title="Modifier l’utilisateur" />

    <form action="{{ route('users.update', $user) }}" method="POST" class="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="name" value="Nom" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="username" value="Nom d'utilisateur" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $user->username)" required />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" value="Nouveau mot de passe (laisser vide pour ne pas changer)" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirmation du mot de passe" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
        </div>
        <div>
            <x-input-label value="Rôles" />
            <div id="roles-group" class="mt-2 grid gap-2 rounded-md border border-neutral-200 bg-neutral-50 p-3">
                @php($selectedRoles = collect((array) old('roles', $user->roleSlugs()))->map(fn ($v) => (string) $v)->all())
                @foreach (\App\Enums\UserRole::cases() as $r)
                    <label class="inline-flex items-center gap-2 text-sm text-neutral-800">
                        <input type="checkbox" name="roles[]" value="{{ $r->value }}" @checked(in_array($r->value, $selectedRoles, true)) class="rounded border-neutral-300 text-primary focus:ring-primary">
                        <span>{{ $r->label() }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('roles')" class="mt-2" />
            <x-input-error :messages="$errors->get('roles.*')" class="mt-2" />
        </div>
        <div id="branch-field" class="space-y-1">
            <x-input-label for="branch_id" value="Branche" />
            <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                <option value="">— Choisir une branche —</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $user->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
        </div>
        <div class="flex gap-3">
            <x-primary-button>Enregistrer</x-primary-button>
            <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Annuler</a>
        </div>
    </form>
    <script>
        (function () {
            var rolesWrap = document.getElementById('roles-group');
            var branchWrap = document.getElementById('branch-field');
            var branchSelect = document.getElementById('branch_id');
            if (!rolesWrap || !branchWrap || !branchSelect) return;
            function sync() {
                var checked = Array.from(rolesWrap.querySelectorAll('input[name="roles[]"]:checked')).map(function (el) { return el.value; });
                var noBranch = checked.includes('admin') || checked.includes('accountant') || checked.includes('logistician');
                branchWrap.classList.toggle('hidden', noBranch);
                branchSelect.disabled = noBranch;
                branchSelect.required = !noBranch;
                if (noBranch) branchSelect.value = '';
            }
            rolesWrap.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>
