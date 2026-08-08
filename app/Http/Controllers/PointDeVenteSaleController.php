<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Location;
use App\Models\PosTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PointDeVenteSaleController extends Controller
{
    use RespectsUserBranch;

    public function chooseBranch(Branch $branch, PosTerminal $posTerminal): View|RedirectResponse
    {
        if ($redirect = $this->ensureFieldPosTerminal($branch, $posTerminal)) {
            return $redirect;
        }

        $openShift = $posTerminal->openShift();
        if ($openShift === null) {
            return redirect()
                ->route('point-de-vente.workspace', [$branch, $posTerminal])
                ->with('warning', 'Ouvrez une session avant de vendre.');
        }

        $branches = $this->fieldSaleEntryBranches();
        $stockLocation = $this->fieldPosStockLocation();

        return view('point_de_vente.choose-branch', compact(
            'branch',
            'posTerminal',
            'branches',
            'stockLocation',
            'openShift',
        ));
    }

    public function chooseLocation(Branch $branch, PosTerminal $posTerminal, Branch $entryBranch): View|RedirectResponse
    {
        if ($redirect = $this->ensureFieldPosTerminal($branch, $posTerminal)) {
            return $redirect;
        }
        $this->ensureFieldSaleEntryBranchAccessible($entryBranch);

        $openShift = $posTerminal->openShift();
        if ($openShift === null) {
            return redirect()
                ->route('point-de-vente.workspace', [$branch, $posTerminal])
                ->with('warning', 'Ouvrez une session avant de vendre.');
        }

        $saleLocations = Location::query()
            ->where('branch_id', $entryBranch->id)
            ->orderBy('name')
            ->get(['id', 'name', 'kind']);

        $stockLocation = $this->fieldPosStockLocation();

        return view('point_de_vente.choose-location', compact(
            'branch',
            'posTerminal',
            'entryBranch',
            'saleLocations',
            'stockLocation',
            'openShift',
        ));
    }

    public function chooseDepartment(
        Branch $branch,
        PosTerminal $posTerminal,
        Branch $entryBranch,
        Location $location,
    ): View|RedirectResponse {
        if ($redirect = $this->ensureFieldPosTerminal($branch, $posTerminal)) {
            return $redirect;
        }
        $this->ensureFieldSaleEntryBranchAccessible($entryBranch);
        abort_unless((int) $location->branch_id === (int) $entryBranch->id, 404);

        $openShift = $posTerminal->openShift();
        if ($openShift === null) {
            return redirect()
                ->route('point-de-vente.workspace', [$branch, $posTerminal])
                ->with('warning', 'Ouvrez une session avant de vendre.');
        }

        $departments = Department::query()
            ->whereHas('products')
            ->orderBy('name')
            ->get(['id', 'name']);

        $stockLocation = $this->fieldPosStockLocation();

        return view('point_de_vente.choose-department', compact(
            'branch',
            'posTerminal',
            'entryBranch',
            'location',
            'departments',
            'stockLocation',
            'openShift',
        ));
    }

    private function ensureFieldPosTerminal(Branch $branch, PosTerminal $posTerminal): ?RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);
        $this->ensurePosTerminalKind($posTerminal, PosTerminal::KIND_FIELD);

        return $this->ensureFieldPosStockConfigured();
    }
}
