<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\PurchaseOrderReceptionBatch;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\View\View;

class PendingActionController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $user = auth()->user();
        $isAdmin = (bool) ($user?->isAdmin());
        $seesAllBranches = (bool) ($user?->canBypassBranchScope());

        $pendingDiscountCount = 0;
        if ($isAdmin) {
            $pendingDiscountQuery = Sale::query()->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
            $this->applyBranchFilter($pendingDiscountQuery, 'branch_id');
            $pendingDiscountCount = (clone $pendingDiscountQuery)->count();
        }

        $pendingReceptionBatchCount = 0;
        if ($isAdmin) {
            $pendingReceptionQuery = PurchaseOrderReceptionBatch::query()
                ->where('status', PurchaseOrderReceptionBatch::STATUS_PENDING)
                ->whereHas('purchaseOrder.location', function ($q) {
                    $this->applyBranchFilter($q);
                });
            $pendingReceptionBatchCount = (clone $pendingReceptionQuery)->count();
        }

        $lowStocksQuery = Stock::query()
            ->with(['product:id,name,department_id', 'location:id,name,branch_id', 'location.branch:id,name'])
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereRaw('COALESCE(stocks.minimum_stock, products.minimum_stock) IS NOT NULL')
            ->whereRaw('stocks.quantity < COALESCE(stocks.minimum_stock, products.minimum_stock)')
            ->select('stocks.*')
            ->orderBy('stocks.quantity');
        $this->applyStockBranchFilter($lowStocksQuery);
        $lowStocksCount = (clone $lowStocksQuery)->count();
        $lowStocks = (clone $lowStocksQuery)->take(8)->get();

        return view('pending_actions.index', compact(
            'isAdmin',
            'seesAllBranches',
            'pendingDiscountCount',
            'pendingReceptionBatchCount',
            'lowStocksCount',
            'lowStocks',
        ));
    }
}
