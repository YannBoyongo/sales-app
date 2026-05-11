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
        $users = User::query()->with(['branch', 'roles'])->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $branches = Branch::orderBy('name')->get();

        return view('users.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
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

        $roleSlugs = collect((array) ($data['roles'] ?? []))
            ->map(fn ($role) => $role instanceof UserRole ? $role->value : (string) $role)
            ->values()
            ->all();

        $primaryRole = $this->primaryRoleSlug($roleSlugs);
        $branchId = $this->resolveBranchIdForRoles($roleSlugs, $data['branch_id'] ?? null);

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
        $branches = Branch::orderBy('name')->get();

        return view('users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
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

        $newRoleSlugs = collect((array) ($data['roles'] ?? []))
            ->map(fn ($role) => $role instanceof UserRole ? $role->value : (string) $role)
            ->values()
            ->all();

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

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $prevBranchId = $user->branch_id;
        $wasPosOrCashier = $user->isPosUser() || $user->isCashier();

        $newBranchId = $this->resolveBranchIdForRoles($newRoleSlugs, $data['branch_id'] ?? null);
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

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé.');
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function primaryRoleSlug(array $roleSlugs): string
    {
        $priority = [
            UserRole::Admin->value,
            UserRole::Accountant->value,
            UserRole::Manager->value,
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
    private function resolveBranchIdForRoles(array $roleSlugs, mixed $branchId): ?int
    {
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
        return in_array(UserRole::Admin->value, $roleSlugs, true)
            || in_array(UserRole::Accountant->value, $roleSlugs, true)
            || in_array(UserRole::Logistician->value, $roleSlugs, true);
    }

    /**
     * @param  list<string>  $roleSlugs
     * @return list<int>
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
