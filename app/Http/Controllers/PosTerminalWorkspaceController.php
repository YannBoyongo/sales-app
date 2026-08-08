<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\PosTerminal;
use App\Models\Sale;
use Illuminate\Http\Request;
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

    public function sales(Request $request, Branch $branch, PosTerminal $posTerminal): View
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'payment_type' => ['nullable', 'in:cash,credit,caution'],
        ]);

        $posTerminal->load('location');

        $sales = Sale::query()
            ->whereHas('posShift', fn ($q) => $q->where('pos_terminal_id', $posTerminal->id))
            ->with([
                'user:id,name',
                'posShift:id,pos_terminal_id,session_date,opened_at,closed_at',
                'posShift.openedByUser:id,name',
            ])
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('sold_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('sold_at', '<=', $value))
            ->when($filters['payment_type'] ?? null, fn ($q, $value) => $q->where('payment_type', $value))
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $canViewClosedShiftDetail = (bool) $request->user()?->canAccessCashDeskFinanceFeatures();

        return view('pos_terminals.sales', compact(
            'branch',
            'posTerminal',
            'sales',
            'filters',
            'canViewClosedShiftDetail',
        ));
    }
}
