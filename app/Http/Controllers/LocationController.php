<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    use RespectsUserBranch;

    public function create(Branch $branch): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('locations.create', compact('branch'));
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in([
                Location::KIND_MAIN,
                Location::KIND_STORAGE,
                Location::KIND_POINT_OF_SALE,
            ])],
        ]);

        $payload = [
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'kind' => $data['kind'],
        ];

        DB::transaction(function () use ($payload) {
            if ($payload['kind'] === Location::KIND_MAIN) {
                Location::query()
                    ->where('branch_id', $payload['branch_id'])
                    ->where('kind', Location::KIND_MAIN)
                    ->update(['kind' => Location::KIND_STORAGE]);
            }
            Location::create($payload);
        });

        return redirect()->route('branches.show', $branch)->with('success', 'Emplacement créé.');
    }

    public function edit(Branch $branch, Location $location): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->ensureLocationBelongsToBranch($location, $branch);

        return view('locations.edit', compact('branch', 'location'));
    }

    public function update(Request $request, Branch $branch, Location $location): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->ensureLocationBelongsToBranch($location, $branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in([
                Location::KIND_MAIN,
                Location::KIND_STORAGE,
                Location::KIND_POINT_OF_SALE,
            ])],
        ]);

        DB::transaction(function () use ($location, $branch, $data) {
            if ($data['kind'] === Location::KIND_MAIN) {
                Location::query()
                    ->where('branch_id', $branch->id)
                    ->where('id', '!=', $location->id)
                    ->where('kind', Location::KIND_MAIN)
                    ->update(['kind' => Location::KIND_STORAGE]);
            }
            $location->update([
                'name' => $data['name'],
                'kind' => $data['kind'],
            ]);
        });

        return redirect()->route('branches.show', $branch)->with('success', 'Emplacement mis à jour.');
    }

    public function destroy(Request $request, Branch $branch, Location $location): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->ensureLocationBelongsToBranch($location, $branch);

        if ($location->isMain()) {
            return redirect()->route('branches.show', $branch)->withErrors([
                'location' => 'Impossible de supprimer l’emplacement principal : désignez d’abord un autre emplacement comme principal.',
            ]);
        }

        if ($location->stocks()->exists()) {
            return redirect()->route('branches.show', $branch)->withErrors([
                'location' => 'Impossible de supprimer : des lignes de stock existent pour cet emplacement.',
            ]);
        }

        $location->delete();

        return redirect()->route('branches.show', $branch)->with('success', 'Emplacement supprimé.');
    }

    private function ensureLocationBelongsToBranch(Location $location, Branch $branch): void
    {
        abort_unless((int) $location->branch_id === (int) $branch->id, 404);
    }
}
