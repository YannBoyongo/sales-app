<?php

namespace App\Http\Controllers;

use App\Support\ActiveBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActiveBranchController extends Controller
{
    public function select(): View|RedirectResponse
    {
        $branches = ActiveBranch::selectableBranches();

        if ($branches->isEmpty()) {
            abort(403, 'Aucune branche accessible pour votre compte.');
        }

        if ($branches->count() === 1) {
            ActiveBranch::set((int) $branches->first()->id);

            return redirect()->intended(route('dashboard'));
        }

        return view('active_branch.select', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        ActiveBranch::set((int) $data['branch_id']);

        return redirect()->intended(route('dashboard'));
    }
}
