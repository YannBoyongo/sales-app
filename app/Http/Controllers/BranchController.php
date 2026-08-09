<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = Branch::query()->withCount('locations')->orderBy('name');
        $this->applyBranchFilter($query);

        $branches = $query->paginate(15);

        return view('branches.index', compact('branches'));
    }

    public function show(Branch $branch): View
    {
        abort_unless(auth()->user()->hasApplicationAdminAccess(), 403);

        $locations = $branch->locations()
            ->with('stockManagers:id,name')
            ->orderBy('name')
            ->paginate(20);

        $terminals = $branch->posTerminals()
            ->with('location')
            ->withCount('posUsers')
            ->orderBy('name')
            ->get();

        return view('branches.show', compact('branch', 'locations', 'terminals'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasApplicationAdminAccess(), 403);

        return view('branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasApplicationAdminAccess(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'can_sell_on_credit' => ['sometimes', 'boolean'],
            'can_apply_discount' => ['sometimes', 'boolean'],
        ]);

        Branch::create([
            'name' => $data['name'],
            'can_sell_on_credit' => $request->user()->isSuperAdmin()
                && $request->boolean('can_sell_on_credit'),
            'can_apply_discount' => $request->user()->isSuperAdmin()
                && $request->boolean('can_apply_discount'),
        ]);

        return redirect()->route('branches.index')->with('success', 'Branche créée.');
    }

    public function edit(Branch $branch): View
    {
        abort_unless(auth()->user()->hasApplicationAdminAccess(), 403);

        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->hasApplicationAdminAccess(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'can_sell_on_credit' => ['sometimes', 'boolean'],
            'can_apply_discount' => ['sometimes', 'boolean'],
        ]);

        $payload = ['name' => $data['name']];

        if ($request->user()->isSuperAdmin()) {
            $payload['can_sell_on_credit'] = $request->boolean('can_sell_on_credit');
            $payload['can_apply_discount'] = $request->boolean('can_apply_discount');
        }

        $branch->update($payload);

        return redirect()->route('branches.show', $branch)->with('success', 'Branche mise à jour.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->hasApplicationAdminAccess(), 403);

        if ($branch->locations()->exists()) {
            return redirect()->route('branches.index')->withErrors([
                'branch' => 'Impossible de supprimer : des emplacements sont liés à cette branche.',
            ]);
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branche supprimée.');
    }
}
