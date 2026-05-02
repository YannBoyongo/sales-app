<?php

namespace App\Http\Controllers;

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
        $isAdmin = $request->boolean('is_admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
            'branch_id' => [
                Rule::requiredIf(! $isAdmin),
                'nullable',
                'exists:branches,id',
            ],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => $isAdmin,
            'branch_id' => $isAdmin ? null : $data['branch_id'],
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
        $willBeAdmin = $request->boolean('is_admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
            'branch_id' => [
                Rule::requiredIf(! $willBeAdmin),
                'nullable',
                'exists:branches,id',
            ],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (! $willBeAdmin && $user->is_admin) {
            $otherAdmins = User::query()->where('is_admin', true)->where('id', '!=', $user->id)->exists();
            if (! $otherAdmins) {
                return back()->withInput()->withErrors([
                    'is_admin' => 'Au moins un administrateur doit rester actif.',
                ]);
            }
        }

        $user->is_admin = $willBeAdmin;
        $user->branch_id = $willBeAdmin ? null : $data['branch_id'];
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

        if ($user->is_admin) {
            $otherAdmins = User::query()->where('is_admin', true)->where('id', '!=', $user->id)->exists();
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
