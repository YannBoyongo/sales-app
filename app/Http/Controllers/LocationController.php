<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = Location::query()->with('branch')->orderBy('name');
        $this->applyBranchFilter($query, 'branch_id');

        $locations = $query->paginate(20);

        return view('locations.index', compact('locations'));
    }

    public function create(): View
    {
        $branches = $this->branchesForUser();

        return view('locations.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->is_admin) {
            $request->merge(['branch_id' => $request->user()->branch_id]);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if (! $request->user()->is_admin) {
            abort_unless((int) $data['branch_id'] === (int) $request->user()->branch_id, 403);
        }

        Location::create($data);

        return redirect()->route('locations.index')->with('success', 'Emplacement créé.');
    }

    public function edit(Location $location): View
    {
        $this->ensureUserCanAccessLocation($location);

        $branches = $this->branchesForUser();

        return view('locations.edit', compact('location', 'branches'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->ensureUserCanAccessLocation($location);

        if (! $request->user()->is_admin) {
            $request->merge(['branch_id' => $request->user()->branch_id]);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if (! $request->user()->is_admin) {
            abort_unless((int) $data['branch_id'] === (int) $request->user()->branch_id, 403);
        }

        $location->update($data);

        return redirect()->route('locations.index')->with('success', 'Emplacement mis à jour.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $this->ensureUserCanAccessLocation($location);

        if ($location->stocks()->exists()) {
            return redirect()->route('locations.index')->withErrors([
                'location' => 'Impossible de supprimer : des lignes de stock existent pour cet emplacement.',
            ]);
        }

        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Emplacement supprimé.');
    }
}
