<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\PosTerminal;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SalesOverviewController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canApproveDiscounts = (bool) ($user?->canApproveSaleDiscounts());

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
            'payment_type' => ['nullable', 'in:cash,credit,caution'],
        ]);

        $posTerminals = $this->posTerminalsForSalesFilter();
        $showsMultipleBranches = $posTerminals->pluck('branch_id')->unique()->count() > 1;

        $sales = Sale::query()
            ->with([
                'branch:id,name',
                'user:id,name',
                'client:id,name',
                'items.product:id,name',
                'posShift:id,pos_terminal_id,session_date,opened_at',
                'posShift.posTerminal:id,name,branch_id',
                'posShift.posTerminal.branch:id,name',
            ]);

        $this->applyBranchFilter($sales, 'branch_id');

        if (! $user?->isAdmin()) {
            $sales->where('user_id', $user->id);
        }

        if ($request->boolean('remise')) {
            $sales->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        }

        $sales
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('sold_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('sold_at', '<=', $value))
            ->when($filters['pos_terminal_id'] ?? null, function ($q, $value) use ($posTerminals) {
                abort_unless($posTerminals->contains('id', (int) $value), 403);
                $q->whereHas('posShift', fn ($shift) => $shift->where('pos_terminal_id', (int) $value));
            })
            ->when($filters['payment_type'] ?? null, fn ($q, $value) => $q->where('payment_type', $value));

        if ($canApproveDiscounts) {
            $sales->orderByRaw('CASE WHEN sale_status = ? THEN 0 ELSE 1 END', [Sale::STATUS_PENDING_DISCOUNT]);
        }

        $sales = $sales
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $pendingDiscountQuery = Sale::query()->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        $this->applyBranchFilter($pendingDiscountQuery, 'branch_id');
        $pendingDiscountCount = $pendingDiscountQuery->count();

        return view('sales.overview', compact(
            'sales',
            'filters',
            'posTerminals',
            'showsMultipleBranches',
            'canApproveDiscounts',
            'pendingDiscountCount',
        ));
    }

    /** @return Collection<int, PosTerminal> */
    private function posTerminalsForSalesFilter(): Collection
    {
        $user = auth()->user();
        if ($user?->isPosUser() || ($user?->isCashier() && $user->posTerminals()->exists())) {
            $assigned = $this->posTerminalsForUser();
            if ($assigned->isNotEmpty()) {
                return $assigned->loadMissing(['branch:id,name', 'location:id,name'])
                    ->sortBy(fn (PosTerminal $t) => ($t->branch->name ?? '').' '.$t->name)
                    ->values();
            }
        }

        $query = PosTerminal::query()
            ->with(['branch:id,name', 'location:id,name'])
            ->orderBy('branch_id')
            ->orderBy('name');

        $this->applyBranchFilter($query, 'branch_id');

        return $query->get();
    }
}
