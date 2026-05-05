<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\AccountingTransaction;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(): View
    {
        $user = auth()->user();
        $user?->loadMissing('branch');

        $isAdmin = (bool) ($user?->isAdmin());
        $isAccountant = (bool) ($user?->isAccountant());
        $seesAllBranches = (bool) ($user?->canBypassBranchScope());
        $canAccessAccounting = (bool) ($user?->canAccessAccounting());
        $userBranch = (! $seesAllBranches && $user?->branch) ? $user->branch : null;

        $weekStart = now()->copy()->subDays(7)->startOfDay();
        $weekSalesQuery = Sale::query()->where('sold_at', '>=', $weekStart);
        $this->applyBranchFilter($weekSalesQuery, 'branch_id');
        $weekSalesCount = (clone $weekSalesQuery)->count();

        $pendingDiscountQuery = Sale::query()->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        $this->applyBranchFilter($pendingDiscountQuery, 'branch_id');
        $pendingDiscountCount = $isAdmin ? (clone $pendingDiscountQuery)->count() : 0;

        $recentSales = Sale::query()
            ->with(['branch', 'user:id,name'])
            ->latest('sold_at');
        $this->applyBranchFilter($recentSales, 'branch_id');
        $recentSales = $recentSales->take(12)->get();

        $branchesCount = $isAdmin ? Branch::query()->count() : null;

        $lowStocksQuery = Stock::query()
            ->with(['product.department', 'location.branch'])
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereRaw('COALESCE(stocks.minimum_stock, products.minimum_stock) IS NOT NULL')
            ->whereRaw('stocks.quantity < COALESCE(stocks.minimum_stock, products.minimum_stock)')
            ->select('stocks.*')
            ->orderBy('stocks.quantity');

        $this->applyStockBranchFilter($lowStocksQuery);

        $lowStocksCount = (clone $lowStocksQuery)->count();
        $lowStocks = (clone $lowStocksQuery)->take(12)->get();

        $startOfDay = now()->copy()->startOfDay();
        $endOfDay = now()->copy()->endOfDay();

        $todayStats = Sale::query()
            ->whereBetween('sold_at', [$startOfDay, $endOfDay]);
        $this->applyBranchFilter($todayStats, 'branch_id');
        $todayStats = $todayStats
            ->selectRaw('
                COUNT(*) as sale_count,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN payment_type = ? THEN total_amount ELSE 0 END), 0) as cash_amount,
                COALESCE(SUM(CASE WHEN payment_type = ? THEN total_amount ELSE 0 END), 0) as credit_amount
            ', ['cash', 'credit'])
            ->first();

        $todaySalesTotal = (string) ($todayStats->total_amount ?? '0');
        $todaySalesCount = (int) ($todayStats->sale_count ?? 0);
        $todayCashTotal = (string) ($todayStats->cash_amount ?? '0');
        $todayCreditTotal = (string) ($todayStats->credit_amount ?? '0');

        $productsCountQuery = Product::query();
        $this->applyProductBranchScope($productsCountQuery);
        $productsCount = $productsCountQuery->count();

        $accountingCaisse = null;
        if ($canAccessAccounting) {
            $ledger = AccountingTransaction::query()
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit,
                    COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) as total_credit
                ")
                ->first();
            $accountingCaisse = bcsub((string) ($ledger->total_debit ?? '0'), (string) ($ledger->total_credit ?? '0'), 2);
        }

        return view('dashboard', compact(
            'recentSales',
            'weekSalesCount',
            'pendingDiscountCount',
            'lowStocks',
            'lowStocksCount',
            'isAdmin',
            'isAccountant',
            'seesAllBranches',
            'canAccessAccounting',
            'userBranch',
            'branchesCount',
            'todaySalesTotal',
            'todaySalesCount',
            'todayCashTotal',
            'todayCreditTotal',
            'productsCount',
            'accountingCaisse',
        ));
    }
}
