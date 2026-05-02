<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\AccountingTransaction;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesSession;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(): View
    {
        $user = auth()->user();
        $user?->loadMissing('branch');

        $isAdmin = (bool) ($user?->is_admin);
        $userBranch = (! $isAdmin && $user?->branch) ? $user->branch : null;

        $openSessionsQuery = SalesSession::query()->where('status', 'open');
        $this->applyBranchFilter($openSessionsQuery);

        $openSessionsCount = (clone $openSessionsQuery)->count();

        $openSessions = (clone $openSessionsQuery)
            ->with(['branch', 'opener'])
            ->latest('opened_at')
            ->take(12)
            ->get();

        $openSessionsByBranch = null;
        if ($isAdmin) {
            $openSessionsByBranch = $openSessions
                ->groupBy('branch_id')
                ->map(function (Collection $sessions) {
                    $first = $sessions->first();

                    return [
                        'branch_name' => $first?->branch?->name ?? '—',
                        'count' => $sessions->count(),
                    ];
                })
                ->values();
        }

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
            ->whereBetween('sold_at', [$startOfDay, $endOfDay])
            ->whereHas('session', function (Builder $q) {
                $this->applyBranchFilter($q);
            })
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
        if ($isAdmin) {
            $ledger = AccountingTransaction::query()
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit,
                    COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) as total_credit
                ")
                ->first();
            $accountingCaisse = bcsub((string) ($ledger->total_debit ?? '0'), (string) ($ledger->total_credit ?? '0'), 2);
        }

        return view('dashboard', compact(
            'openSessions',
            'openSessionsCount',
            'openSessionsByBranch',
            'lowStocks',
            'lowStocksCount',
            'isAdmin',
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
