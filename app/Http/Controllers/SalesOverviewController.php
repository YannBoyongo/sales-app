<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesOverviewController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canApproveDiscounts = (bool) ($user?->canApproveSaleDiscounts());

        $sales = Sale::query()
            ->with(['branch', 'user:id,name', 'client:id,name']);

        $this->applyBranchFilter($sales, 'branch_id');

        if ($request->boolean('remise')) {
            $sales->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        }

        if ($canApproveDiscounts) {
            $sales->orderByRaw('CASE WHEN sale_status = ? THEN 0 ELSE 1 END', [Sale::STATUS_PENDING_DISCOUNT]);
        }

        $sales = $sales
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        $pendingDiscountQuery = Sale::query()->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        $this->applyBranchFilter($pendingDiscountQuery, 'branch_id');
        $pendingDiscountCount = $pendingDiscountQuery->count();

        $canManageSales = (bool) ($user?->isAdmin());

        return view('sales.overview', compact(
            'sales',
            'canApproveDiscounts',
            'pendingDiscountCount',
            'canManageSales',
        ));
    }
}
