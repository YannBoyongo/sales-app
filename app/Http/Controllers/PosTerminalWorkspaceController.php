<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\PosTerminal;
use Illuminate\View\View;

class PosTerminalWorkspaceController extends Controller
{
    use RespectsUserBranch;

    public function show(Branch $branch, PosTerminal $posTerminal): View
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $posTerminal->load('location');
        $openShift = $posTerminal->openShift();
        $shiftSales = collect();
        if ($openShift) {
            $openShift->load(['openedByUser:id,name']);
            $openShift->alignSalesSoldAtToSessionDate();
            $shiftSales = $openShift->sales()
                ->with(['user:id,name', 'posShift:id,pos_terminal_id,session_date,opened_at'])
                ->orderByDesc('sold_at')
                ->orderByDesc('id')
                ->get();
        }

        $canPickAnotherBranch = $this->branchesForUser()->count() > 1;

        return view('pos_terminals.workspace', compact(
            'branch',
            'posTerminal',
            'openShift',
            'shiftSales',
            'canPickAnotherBranch',
        ));
    }
}
