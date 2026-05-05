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
        abort_unless(auth()->user()->isAdmin(), 403);

        $locations = $branch->locations()->orderBy('name')->paginate(20);

        $terminals = $branch->posTerminals()
            ->with('location')
            ->withCount('posUsers')
            ->orderBy('name')
            ->get();

        return view('branches.show', compact('branch', 'locations', 'terminals'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Branch::create($data);

        return redirect()->route('branches.index')->with('success', 'Branche créée.');
    }

    public function edit(Branch $branch): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $branch->update($data);

        return redirect()->route('branches.show', $branch)->with('success', 'Branche mise à jour.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($branch->locations()->exists()) {
            return redirect()->route('branches.index')->withErrors([
                'branch' => 'Impossible de supprimer : des emplacements sont liés à cette branche.',
            ]);
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branche supprimée.');
    }
}
