<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = Department::query()->withCount('products')->orderBy('name');
        $this->applyDepartmentBranchFilter($query);

        $departments = $query->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Department::create($data);

        return redirect()->route('departments.index')->with('success', 'Département créé.');
    }

    public function edit(Department $department): View
    {
        $this->ensureDepartmentAccessibleForBranchUser($department);

        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->ensureDepartmentAccessibleForBranchUser($department);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department->update($data);

        return redirect()->route('departments.index')->with('success', 'Département mis à jour.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->ensureDepartmentAccessibleForBranchUser($department);

        if ($department->products()->exists()) {
            return redirect()->route('departments.index')->withErrors([
                'department' => 'Impossible de supprimer : des produits sont rattachés à ce département.',
            ]);
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Département supprimé.');
    }
}
