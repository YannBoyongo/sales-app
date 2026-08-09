<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $this->authorizeUserManagement();

        $query = User::query()->with(['branch', 'roles'])->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('slug', UserRole::SuperAdmin->value));
        }

        if (auth()->user()->isBranchAdmin()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $users = $query->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorizeUserManagement();

        $branches = $this->branchesForUserManagement();
        $assignableRoles = auth()->user()->assignableRoles();

        return view('users.create', compact('branches', 'assignableRoles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUserManagement();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::enum(UserRole::class)],
            'branch_id' => [
                Rule::requiredIf(function () use ($request) {
                    $selected = array_map('strval', (array) $request->input('roles', []));

                    return ! $this->branchIsOptionalForRoleSlugs($selected);
                }),
                'nullable',
                'exists:branches,id',
            ],
        ]);

        $roleSlugs = $this->normalizeRoleSlugs((array) ($data['roles'] ?? []));

        if ($error = $this->validateAssignableRoleSlugs($roleSlugs)) {
            return back()->withInput()->withErrors(['roles' => $error]);
        }

        $primaryRole = $this->primaryRoleSlug($roleSlugs);
        $branchId = $this->resolveBranchIdForRoles($roleSlugs, $data['branch_id'] ?? null, auth()->user());

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $primaryRole,
            'branch_id' => $branchId,
        ]);
        $user->roles()->sync($this->roleIdsFromSlugs($roleSlugs));

        return redirect()->route('users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user): View
    {
        $this->authorizeUserManagement();
        $this->authorizeManageTargetUser($user);

        $branches = $this->branchesForUserManagement();
        $assignableRoles = auth()->user()->assignableRoles();

        return view('users.edit', compact('user', 'branches', 'assignableRoles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserManagement();
        $this->authorizeManageTargetUser($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::enum(UserRole::class)],
            'branch_id' => [
                Rule::requiredIf(function () use ($request) {
                    $selected = array_map('strval', (array) $request->input('roles', []));

                    return ! $this->branchIsOptionalForRoleSlugs($selected);
                }),
                'nullable',
                'exists:branches,id',
            ],
        ]);

        $newRoleSlugs = $this->normalizeRoleSlugs((array) ($data['roles'] ?? []));

        if ($error = $this->validateAssignableRoleSlugs($newRoleSlugs)) {
            return back()->withInput()->withErrors(['roles' => $error]);
        }

        if ($user->isAdmin() && ! in_array(UserRole::Admin->value, $newRoleSlugs, true)) {
            $otherAdmins = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::Admin->value))
                ->exists();
            if (! $otherAdmins) {
                return back()->withInput()->withErrors([
                    'roles' => 'Au moins un administrateur doit rester actif.',
                ]);
            }
        }

        if ($user->isSuperAdmin() && ! in_array(UserRole::SuperAdmin->value, $newRoleSlugs, true)) {
            $otherSuperAdmins = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::SuperAdmin->value))
                ->exists();
            if (! $otherSuperAdmins) {
                return back()->withInput()->withErrors([
                    'roles' => 'Au moins un super administrateur doit rester actif.',
                ]);
            }
        }

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $prevBranchId = $user->branch_id;
        $wasPosOrCashier = $user->isPosUser() || $user->isCashier();

        $newBranchId = $this->resolveBranchIdForRoles($newRoleSlugs, $data['branch_id'] ?? null, auth()->user());
        $newHasPosOrCashier = in_array(UserRole::PosUser->value, $newRoleSlugs, true)
            || in_array(UserRole::Cashier->value, $newRoleSlugs, true);

        $user->role = $this->primaryRoleSlug($newRoleSlugs);

        if ($wasPosOrCashier && ! $newHasPosOrCashier) {
            $user->posTerminals()->detach();
        }

        $user->branch_id = $newBranchId;

        if ($newHasPosOrCashier && (int) ($prevBranchId ?? 0) !== (int) ($newBranchId ?? 0)) {
            $user->posTerminals()->detach();
        }

        $user->save();
        $user->roles()->sync($this->roleIdsFromSlugs($newRoleSlugs));

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserManagement();
        $this->authorizeManageTargetUser($user);

        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->withErrors([
                'user' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        if ($user->isAdmin()) {
            $otherAdmins = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::Admin->value))
                ->exists();
            if (! $otherAdmins) {
                return redirect()->route('users.index')->withErrors([
                    'user' => 'Impossible de supprimer le dernier administrateur.',
                ]);
            }
        }

        if ($user->isSuperAdmin()) {
            $otherSuperAdmins = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::SuperAdmin->value))
                ->exists();
            if (! $otherSuperAdmins) {
                return redirect()->route('users.index')->withErrors([
                    'user' => 'Impossible de supprimer le dernier super administrateur.',
                ]);
            }
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé.');
    }

    private function authorizeUserManagement(): void
    {
        abort_unless(auth()->user()?->canManageUsers(), 403);
    }

    private function authorizeManageTargetUser(User $user): void
    {
        $actor = auth()->user();

        if ($actor?->isSuperAdmin()) {
            return;
        }

        if ($user->isProtectedFromRegularAdmin()) {
            abort(403, 'Seul un super administrateur peut modifier ce compte.');
        }

        if ($actor?->isBranchAdmin()) {
            if ((int) ($user->branch_id ?? 0) !== (int) $actor->branch_id) {
                abort(403, 'Vous ne pouvez gérer que les utilisateurs de votre branche.');
            }
        }
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function validateAssignableRoleSlugs(array $roleSlugs): ?string
    {
        $allowed = collect(auth()->user()->assignableRoles())->map->value->all();
        $invalid = array_diff($roleSlugs, $allowed);

        if ($invalid !== []) {
            return 'Vous ne pouvez pas attribuer ces rôles.';
        }

        if ($roleSlugs === []) {
            return 'Au moins un rôle est requis.';
        }

        return null;
    }

    /**
     * @param  list<mixed>  $roles
     * @return list<string>
     */
    private function normalizeRoleSlugs(array $roles): array
    {
        return collect($roles)
            ->map(fn ($role) => $role instanceof UserRole ? $role->value : (string) $role)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function primaryRoleSlug(array $roleSlugs): string
    {
        $priority = [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Accountant->value,
            UserRole::Manager->value,
            UserRole::StockManager->value,
            UserRole::Logistician->value,
            UserRole::Cashier->value,
            UserRole::PosUser->value,
        ];

        foreach ($priority as $slug) {
            if (in_array($slug, $roleSlugs, true)) {
                return $slug;
            }
        }

        return UserRole::Manager->value;
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function resolveBranchIdForRoles(array $roleSlugs, mixed $branchId, ?User $actor = null): ?int
    {
        if ($actor?->isBranchAdmin()) {
            return (int) $actor->branch_id;
        }

        if ($this->branchIsOptionalForRoleSlugs($roleSlugs)) {
            return null;
        }

        return $branchId !== null && $branchId !== '' ? (int) $branchId : null;
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function branchIsOptionalForRoleSlugs(array $roleSlugs): bool
    {
        return in_array(UserRole::SuperAdmin->value, $roleSlugs, true)
            || in_array(UserRole::Admin->value, $roleSlugs, true)
            || in_array(UserRole::Accountant->value, $roleSlugs, true)
            || in_array(UserRole::Logistician->value, $roleSlugs, true)
            || in_array(UserRole::StockManager->value, $roleSlugs, true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    private function branchesForUserManagement(): \Illuminate\Support\Collection
    {
        $actor = auth()->user();

        if ($actor?->isBranchAdmin()) {
            return Branch::query()
                ->whereKey($actor->branch_id)
                ->orderBy('name')
                ->get();
        }

        return Branch::orderBy('name')->get();
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function roleIdsFromSlugs(array $roleSlugs): array
    {
        // Keep pivot slugs aligned with UserRole enum (e.g. logistician added after first migrate).
        (new RoleSeeder)->run();

        return Role::query()
            ->whereIn('slug', $roleSlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
