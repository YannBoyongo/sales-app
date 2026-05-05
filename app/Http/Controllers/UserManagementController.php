<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('branch')->orderBy('name')->paginate(20);

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
            'role' => ['required', Rule::enum(UserRole::class)],
            'branch_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), [UserRole::User->value, UserRole::PosUser->value], true)),
                'nullable',
                'exists:branches,id',
            ],
        ]);

        /** @var UserRole $role */
        $role = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from((string) $data['role']);

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role,
            'branch_id' => ($role === UserRole::Admin || $role === UserRole::Accountant) ? null : ($data['branch_id'] ?? null),
        ]);

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
            'role' => ['required', Rule::enum(UserRole::class)],
            'branch_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), [UserRole::User->value, UserRole::PosUser->value], true)),
                'nullable',
                'exists:branches,id',
            ],
        ]);

        /** @var UserRole $newRole */
        $newRole = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from((string) $data['role']);

        if ($user->isAdmin() && $newRole !== UserRole::Admin) {
            $otherAdmins = User::query()->where('role', UserRole::Admin)->where('id', '!=', $user->id)->exists();
            if (! $otherAdmins) {
                return back()->withInput()->withErrors([
                    'role' => 'Au moins un administrateur doit rester actif.',
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
        $wasPosUser = $user->isPosUser();

        $user->role = $newRole;
        $newBranchId = ($newRole === UserRole::Admin || $newRole === UserRole::Accountant) ? null : ($data['branch_id'] ?? null);

        if ($wasPosUser && $newRole !== UserRole::PosUser) {
            $user->posTerminals()->detach();
        }

        $user->branch_id = $newBranchId;

        if ($newRole === UserRole::PosUser && (int) ($prevBranchId ?? 0) !== (int) ($newBranchId ?? 0)) {
            $user->posTerminals()->detach();
        }

        $user->save();

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
            $otherAdmins = User::query()->where('role', UserRole::Admin)->where('id', '!=', $user->id)->exists();
            if (! $otherAdmins) {
                return redirect()->route('users.index')->withErrors([
                    'user' => 'Impossible de supprimer le dernier administrateur.',
                ]);
            }
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé.');
    }
}
