<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Location;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function create(Branch $branch): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $stockManagerCandidates = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::StockManager->value))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('locations.create', compact('branch', 'stockManagerCandidates'));
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
            'stock_manager_ids' => ['nullable', 'array'],
            'stock_manager_ids.*' => $this->stockManagerUserIdRules(),
        ]);

        $payload = [
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'kind' => $data['kind'],
        ];

        $location = DB::transaction(function () use ($payload) {
            if ($payload['kind'] === Location::KIND_MAIN) {
                Location::query()
                    ->where('branch_id', $payload['branch_id'])
                    ->where('kind', Location::KIND_MAIN)
                    ->update(['kind' => Location::KIND_STORAGE]);
            }

            return Location::create($payload);
        });

        $syncIds = array_map('intval', $data['stock_manager_ids'] ?? []);
        sort($syncIds);
        $location->stockManagers()->sync($syncIds);

        return redirect()->route('branches.show', $branch)->with('success', 'Emplacement créé.');
    }

    public function edit(Branch $branch, Location $location): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->ensureLocationBelongsToBranch($location, $branch);

        $stockManagerCandidates = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::StockManager->value))
            ->orderBy('name')
            ->get(['id', 'name']);

        $location->load('stockManagers');

        return view('locations.edit', compact('branch', 'location', 'stockManagerCandidates'));
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
            'stock_manager_ids' => ['nullable', 'array'],
            'stock_manager_ids.*' => $this->stockManagerUserIdRules(),
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

        $syncIds = array_map('intval', $data['stock_manager_ids'] ?? []);
        sort($syncIds);
        $location->stockManagers()->sync($syncIds);

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

    /**
     * @return list<int|string|Closure>
     */
    private function stockManagerUserIdRules(): array
    {
        return [
            'integer',
            function (string $attribute, mixed $value, Closure $fail): void {
                $ok = User::query()
                    ->whereKey($value)
                    ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::StockManager->value))
                    ->exists();
                if (! $ok) {
                    $fail('L’utilisateur choisi n’est pas un magasinier valide.');
                }
            },
        ];
    }
}
