<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsFinancialReports;
use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditSalesListReportController extends Controller
{
    use BuildsFinancialReports;
    use RespectsUserBranch;
    use RendersReportPdf;

    public function __invoke(Request $request): View
    {
        $data = $this->buildCreditReportData($request);

        $rows = (clone $data['baseQuery'])
            ->select($data['rowSelect'])
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->orderBy('products.name')
            ->paginate(50)
            ->withQueryString();

        return view('reports.credit-sales', [
            'rows' => $rows,
            'filters' => $data['filters'],
            'summaryQuantity' => $data['summaryQuantity'],
            'summaryAmount' => $data['summaryAmount'],
            'summaryRemaining' => $data['summaryRemaining'],
        ]);
    }

    public function pdf(Request $request): Response
    {
        $data = $this->buildCreditReportData($request);

        $rows = (clone $data['baseQuery'])
            ->select($data['rowSelect'])
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->orderBy('products.name')
            ->get();

        return $this->streamReportPdf('reports.pdf.credit-sales', [
            'title' => 'Liste des ventes crédit',
            'period' => $this->formatReportPeriod($data['filters']['date_from'], $data['filters']['date_to']),
            'rows' => $rows,
            'summaryQuantity' => $data['summaryQuantity'],
            'summaryAmount' => $data['summaryAmount'],
            'summaryRemaining' => $data['summaryRemaining'],
        ], 'liste-ventes-credit', 'landscape');
    }

    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     baseQuery: Builder,
     *     rowSelect: array<int, mixed>,
     *     summaryQuantity: int,
     *     summaryAmount: float,
     *     summaryRemaining: float
     * }
     */
    private function buildCreditReportData(Request $request): array
    {
        $this->authorizeFinancialReport();
        $filters = $this->resolveReportDateFilters($request);

        $baseQuery = $this->creditSalesReportBaseQuery($filters['date_from'], $filters['date_to']);

        $summaryQuantity = (int) (clone $baseQuery)->sum('sale_items.quantity');
        $summaryAmount = (float) (clone $baseQuery)->sum('sale_items.line_total');
        $summaryRemaining = $this->summarizeCreditRemaining($filters['date_from'], $filters['date_to']);

        $rowSelect = [
            'sale_items.product_id',
            'products.name as product_name',
            'products.sku as product_sku',
            DB::raw('MAX(sales.sold_at) as movement_date'),
            DB::raw("GROUP_CONCAT(DISTINCT DATE_FORMAT(sales.credit_due_date, '%d/%m/%Y') ORDER BY sales.credit_due_date SEPARATOR ', ') as due_dates"),
            DB::raw('SUM(sale_items.quantity) as total_quantity'),
            DB::raw('SUM(sale_items.line_total) as total_amount'),
            DB::raw('GROUP_CONCAT(DISTINCT COALESCE(clients.name, sales.client_name) ORDER BY clients.name SEPARATOR ", ") as clients'),
        ];

        return compact(
            'filters',
            'baseQuery',
            'rowSelect',
            'summaryQuantity',
            'summaryAmount',
            'summaryRemaining',
        );
    }

    private function creditSalesReportBaseQuery(string $dateFrom, string $dateTo): Builder
    {
        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('clients', 'clients.id', '=', 'sales.client_id')
            ->where('sales.payment_type', 'credit')
            ->whereDate('sales.sold_at', '>=', $dateFrom)
            ->whereDate('sales.sold_at', '<=', $dateTo);

        $this->applyBranchFilter($query, 'sale_items.branch_id');

        $user = auth()->user();
        if (! $user?->isAdmin() && ! $user?->canAccessCashDeskFinanceFeatures()) {
            $query->where('sale_items.user_id', $user->id);
        }

        return $query;
    }

    private function summarizeCreditRemaining(string $dateFrom, string $dateTo): float
    {
        $query = Sale::query()
            ->where('payment_type', 'credit')
            ->whereDate('sold_at', '>=', $dateFrom)
            ->whereDate('sold_at', '<=', $dateTo);

        $this->applyBranchFilter($query, 'branch_id');

        $user = auth()->user();
        if (! $user?->isAdmin() && ! $user?->canAccessCashDeskFinanceFeatures()) {
            $query->where('user_id', $user->id);
        }

        $remaining = '0.00';

        $query
            ->select([
                'id',
                'subtotal_amount',
                'total_amount',
                'discount_amount',
                'amount_paid',
                'payment_type',
                'payment_status',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($sales) use (&$remaining) {
                foreach ($sales as $sale) {
                    $remaining = bcadd($remaining, $sale->remainingAmountValue(), 2);
                }
            });

        return (float) $remaining;
    }
}
