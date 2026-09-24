<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\AccountingTransaction;
use App\Support\ActiveBranch;
use App\Models\Product;
use App\Models\PurchaseOrderReceptionBatch;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(Request $request): View
    {
        $user = auth()->user();
        $user?->loadMissing('branch');

        $isAdmin = (bool) ($user?->hasApplicationAdminAccess());
        $isAccountant = (bool) ($user?->isAccountant());
        $seesAllBranches = (bool) ($user?->canBypassBranchScope());
        $canAccessAccounting = (bool) ($user?->canAccessAccounting());
        $userBranch = ActiveBranch::branch() ?? ($user?->branch);

        $weekStart = now()->copy()->subDays(7)->startOfDay();
        $weekSalesQuery = Sale::query()->where('sold_at', '>=', $weekStart);
        $this->applyBranchFilter($weekSalesQuery, 'branch_id');
        $weekSalesCount = (clone $weekSalesQuery)->count();

        $pendingDiscountQuery = Sale::query()->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        $this->applyBranchFilter($pendingDiscountQuery, 'branch_id');
        $pendingDiscountCount = $isAdmin ? (clone $pendingDiscountQuery)->count() : 0;

        $pendingReceptionBatchCount = 0;
        if ($isAdmin) {
            $pendingReceptionQuery = PurchaseOrderReceptionBatch::query()
                ->where('status', PurchaseOrderReceptionBatch::STATUS_PENDING)
                ->whereHas('purchaseOrder.location', function ($q) {
                    $this->applyBranchFilter($q);
                });
            $pendingReceptionBatchCount = (clone $pendingReceptionQuery)->count();
        }

        $recentSales = Sale::query()
            ->with(['branch', 'user:id,name'])
            ->latest('sold_at');
        $this->applyBranchFilter($recentSales, 'branch_id');
        $recentSales = $recentSales->take(5)->get();

        $branchesCount = $isAdmin ? $this->selectableBranchesForUser()->count() : null;

        $lowStocksQuery = Stock::query()
            ->with(['product.department', 'location.branch'])
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereHas('location')
            ->whereHas('product')
            ->whereRaw('COALESCE(stocks.minimum_stock, products.minimum_stock) IS NOT NULL')
            ->whereRaw('stocks.quantity < COALESCE(stocks.minimum_stock, products.minimum_stock)')
            ->select('stocks.*')
            ->orderBy('stocks.quantity');

        $this->applyStockBranchFilter($lowStocksQuery);

        $lowStocksCount = (clone $lowStocksQuery)->count();
        $lowStocks = (clone $lowStocksQuery)->take(5)->get();

        $productsInStockQuery = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->join('departments', 'departments.id', '=', 'products.department_id')
            ->whereHas('location')
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.sku as product_sku,
                departments.id as department_id,
                departments.name as department_name,
                SUM(stocks.quantity) as total_quantity
            ')
            ->groupBy('products.id', 'products.name', 'products.sku', 'departments.id', 'departments.name')
            ->havingRaw('SUM(stocks.quantity) > 0')
            ->orderBy('departments.name')
            ->orderBy('products.name');

        $this->applyStockBranchFilter($productsInStockQuery);
        $productsInStockRows = $productsInStockQuery->get();
        $productsInStockCount = $productsInStockRows->count();

        $stockByDepartment = $productsInStockRows
            ->groupBy('department_id')
            ->map(function ($products) {
                $first = $products->first();

                return [
                    'id' => (int) $first->department_id,
                    'name' => (string) $first->department_name,
                    'product_count' => $products->count(),
                    'total_quantity' => (int) $products->sum('total_quantity'),
                    'products' => $products->map(fn ($row) => [
                        'product_id' => (int) $row->product_id,
                        'product_name' => (string) $row->product_name,
                        'product_sku' => $row->product_sku ? (string) $row->product_sku : null,
                        'total_quantity' => (int) $row->total_quantity,
                    ])->values()->all(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

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
            $ledger = AccountingTransaction::query();
            $this->applyBranchFilter($ledger, 'accounting_transactions.branch_id');
            $ledger = $ledger
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit,
                    COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) as total_credit
                ")
                ->first();
            $accountingCaisse = bcsub((string) ($ledger->total_debit ?? '0'), (string) ($ledger->total_credit ?? '0'), 2);
        }

        $monthlySalesTrend = null;
        $branchSalesChart = null;
        $salesYearOptions = [];
        $salesMonthOptions = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];
        $selectedSalesYear = (int) now()->year;
        $selectedSalesMonth = (int) now()->month;

        if ($isAdmin) {
            $yearBoundsQuery = Sale::query();
            $this->applyBranchFilter($yearBoundsQuery, 'branch_id');
            $yearBounds = $yearBoundsQuery
                ->selectRaw('MIN(YEAR(sold_at)) as min_year, MAX(YEAR(sold_at)) as max_year')
                ->first();

            $minYear = (int) ($yearBounds->min_year ?? now()->year);
            $maxYear = max((int) ($yearBounds->max_year ?? now()->year), (int) now()->year);
            if ($minYear < 2000) {
                $minYear = (int) now()->year;
            }

            $salesYearOptions = range($maxYear, $minYear);

            $filters = $request->validate([
                'sales_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'sales_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            ]);

            $selectedSalesYear = (int) ($filters['sales_year'] ?? now()->year);
            $selectedSalesMonth = (int) ($filters['sales_month'] ?? now()->month);

            if (! in_array($selectedSalesYear, $salesYearOptions, true)) {
                $selectedSalesYear = (int) now()->year;
                if (! in_array($selectedSalesYear, $salesYearOptions, true)) {
                    $salesYearOptions[] = $selectedSalesYear;
                    rsort($salesYearOptions);
                }
            }

            $monthStart = now()->copy()->setDate($selectedSalesYear, $selectedSalesMonth, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $daysInMonth = (int) $monthStart->daysInMonth;

            $dailyRowsQuery = Sale::query()
                ->whereBetween('sold_at', [$monthStart, $monthEnd]);
            $this->applyBranchFilter($dailyRowsQuery, 'branch_id');
            $dailyRows = $dailyRowsQuery
                ->selectRaw('DATE(sold_at) as sale_date, COUNT(*) as sale_count, COALESCE(SUM(total_amount), 0) as total_amount')
                ->groupByRaw('DATE(sold_at)')
                ->orderBy('sale_date')
                ->get()
                ->keyBy(fn ($row) => (string) $row->sale_date);

            $labels = [];
            $amounts = [];
            $counts = [];
            $monthTotalAmount = 0.0;
            $monthTotalCount = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = $monthStart->copy()->day($day);
                $key = $date->toDateString();
                $row = $dailyRows->get($key);
                $amount = round((float) ($row->total_amount ?? 0), 2);
                $count = (int) ($row->sale_count ?? 0);

                $labels[] = (string) $day;
                $amounts[] = $amount;
                $counts[] = $count;
                $monthTotalAmount += $amount;
                $monthTotalCount += $count;
            }

            $monthlySalesTrend = [
                'labels' => $labels,
                'amounts' => $amounts,
                'counts' => $counts,
                'month_label' => $salesMonthOptions[$selectedSalesMonth].' '.$selectedSalesYear,
                'total_amount' => round($monthTotalAmount, 2),
                'total_count' => $monthTotalCount,
            ];

            $paymentTypeLabels = [
                'cash' => 'Espèces',
                'credit' => 'Crédit',
                'caution' => 'Caution',
            ];

            $paymentRowsQuery = Sale::query()
                ->whereBetween('sold_at', [$monthStart, $monthEnd]);
            $this->applyBranchFilter($paymentRowsQuery, 'branch_id');
            $paymentRows = $paymentRowsQuery
                ->selectRaw('payment_type, COUNT(*) as sale_count, COALESCE(SUM(total_amount), 0) as total_amount')
                ->groupBy('payment_type')
                ->get()
                ->keyBy(fn ($row) => (string) $row->payment_type);

            $branchLabels = [];
            $branchAmounts = [];
            $branchCounts = [];

            foreach ($paymentTypeLabels as $type => $label) {
                $row = $paymentRows->get($type);
                $amount = round((float) ($row->total_amount ?? 0), 2);
                $count = (int) ($row->sale_count ?? 0);
                if ($amount <= 0 && $count <= 0) {
                    continue;
                }
                $branchLabels[] = $label;
                $branchAmounts[] = $amount;
                $branchCounts[] = $count;
            }

            foreach ($paymentRows as $type => $row) {
                if (isset($paymentTypeLabels[(string) $type])) {
                    continue;
                }
                $amount = round((float) ($row->total_amount ?? 0), 2);
                $count = (int) ($row->sale_count ?? 0);
                if ($amount <= 0 && $count <= 0) {
                    continue;
                }
                $branchLabels[] = ucfirst((string) $type);
                $branchAmounts[] = $amount;
                $branchCounts[] = $count;
            }

            $branchSalesChart = [
                'labels' => $branchLabels,
                'amounts' => $branchAmounts,
                'counts' => $branchCounts,
                'month_label' => $salesMonthOptions[$selectedSalesMonth].' '.$selectedSalesYear,
            ];
        }

        $creditDueSoonDays = 7;
        $today = now()->startOfDay();
        $creditDueHorizon = now()->copy()->addDays($creditDueSoonDays)->endOfDay();

        $creditDueSalesQuery = Sale::query()
            ->with(['branch:id,name', 'client:id,name'])
            ->where('payment_type', 'credit')
            ->whereNotNull('credit_due_date')
            ->whereDate('credit_due_date', '<=', $creditDueHorizon)
            ->whereIn('payment_status', [
                Sale::PAYMENT_STATUS_NOT_PAID,
                Sale::PAYMENT_STATUS_PARTIALLY_PAID,
            ])
            ->orderBy('credit_due_date')
            ->orderBy('reference');

        $this->applyBranchFilter($creditDueSalesQuery, 'branch_id');

        $creditDueReached = collect();
        $creditDueSoon = collect();

        $creditDueSalesQuery->chunk(100, function ($sales) use ($today, &$creditDueReached, &$creditDueSoon) {
            foreach ($sales as $sale) {
                if (bccomp($sale->remainingAmountValue(), '0', 2) !== 1) {
                    continue;
                }

                if ($sale->credit_due_date->lte($today)) {
                    $creditDueReached->push($sale);
                } else {
                    $creditDueSoon->push($sale);
                }
            }
        });

        return view('dashboard', compact(
            'recentSales',
            'weekSalesCount',
            'pendingDiscountCount',
            'pendingReceptionBatchCount',
            'lowStocks',
            'lowStocksCount',
            'stockByDepartment',
            'productsInStockCount',
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
            'monthlySalesTrend',
            'branchSalesChart',
            'salesYearOptions',
            'salesMonthOptions',
            'selectedSalesYear',
            'selectedSalesMonth',
            'creditDueReached',
            'creditDueSoon',
            'creditDueSoonDays',
        ));
    }
}
