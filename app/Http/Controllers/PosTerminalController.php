<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosTerminalController extends Controller
{
    public function index(Branch $branch): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $terminals = $branch->posTerminals()
            ->with('location')
            ->withCount('posUsers')
            ->get();

        return view('pos_terminals.index', compact('branch', 'terminals'));
    }

    public function create(Branch $branch): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $usedLocationIds = PosTerminal::query()->where('branch_id', $branch->id)->pluck('location_id');
        $locations = Location::query()
            ->where('branch_id', $branch->id)
            ->pointOfSale()
            ->whereNotIn('id', $usedLocationIds)
            ->orderBy('name')
            ->get();

        $eligibleUsers = User::query()
            ->where('branch_id', $branch->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['pos_user', 'cashier']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pos_terminals.create', compact('branch', 'locations', 'eligibleUsers'));
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $usedLocationIds = PosTerminal::query()->where('branch_id', $branch->id)->pluck('location_id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(function ($q) use ($branch, $usedLocationIds) {
                    $q->where('branch_id', $branch->id)
                        ->where('kind', Location::KIND_POINT_OF_SALE)
                        ->whereNotIn('id', $usedLocationIds);
                }),
            ],
            'pos_user_ids' => ['nullable', 'array'],
            'pos_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $terminal = PosTerminal::create([
            'branch_id' => $branch->id,
            'location_id' => (int) $data['location_id'],
            'name' => $data['name'],
        ]);

        $this->syncPosUsers($terminal, $data['pos_user_ids'] ?? []);

        return redirect()
            ->route('branches.pos-terminals.index', $branch)
            ->with('success', 'Terminal POS créé.');
    }

    public function edit(Branch $branch, PosTerminal $posTerminal): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless((int) $posTerminal->branch_id === (int) $branch->id, 404);

        $posTerminal->load('posUsers');
        $eligibleUsers = User::query()
            ->where('branch_id', $branch->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['pos_user', 'cashier']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pos_terminals.edit', compact('branch', 'posTerminal', 'eligibleUsers'));
    }

    public function update(Request $request, Branch $branch, PosTerminal $posTerminal): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless((int) $posTerminal->branch_id === (int) $branch->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pos_user_ids' => ['nullable', 'array'],
            'pos_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $posTerminal->update(['name' => $data['name']]);

        $this->syncPosUsers($posTerminal, $data['pos_user_ids'] ?? []);

        return redirect()
            ->route('branches.pos-terminals.index', $branch)
            ->with('success', 'Terminal POS mis à jour.');
    }

    private function syncPosUsers(PosTerminal $terminal, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $allowed = User::query()
            ->whereIn('id', $userIds)
            ->where('branch_id', $terminal->branch_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['pos_user', 'cashier']))
            ->pluck('id')
            ->all();

        $terminal->posUsers()->sync($allowed);
    }

    public function destroy(Branch $branch, PosTerminal $posTerminal): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless((int) $posTerminal->branch_id === (int) $branch->id, 404);

        if ($posTerminal->openShift() !== null) {
            return redirect()
                ->route('branches.pos-terminals.index', $branch)
                ->withErrors(['terminal' => 'Fermez la session de caisse avant de supprimer ce terminal.']);
        }

        if (Sale::query()
            ->whereHas('posShift', fn ($q) => $q->where('pos_terminal_id', $posTerminal->id))
            ->exists()) {
            return redirect()
                ->route('branches.pos-terminals.index', $branch)
                ->withErrors(['terminal' => 'Impossible de supprimer un terminal ayant des ventes liées à une session.']);
        }

        $posTerminal->delete();

        return redirect()
            ->route('branches.pos-terminals.index', $branch)
            ->with('success', 'Terminal supprimé.');
    }
}
