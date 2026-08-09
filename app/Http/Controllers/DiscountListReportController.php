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

class DiscountListReportController extends Controller
{
    use BuildsFinancialReports;
    use RespectsUserBranch;
    use RendersReportPdf;

    public function __invoke(Request $request): View
    {
        $data = $this->buildDiscountReportData($request);

        $rows = (clone $data['discountedQuery'])
            ->join('branches', 'branches.id', '=', 'sale_items.branch_id')
            ->join('locations', 'locations.id', '=', 'sale_items.location_id')
            ->join('users', 'users.id', '=', 'sale_items.user_id')
            ->select($data['rowSelect'])
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sale_items.id')
            ->paginate(50)
            ->withQueryString();

        return view('reports.discounts', [
            'rows' => $rows,
            'filters' => $data['filters'],
            'summaryOriginal' => $data['summaryOriginal'],
            'summaryApproved' => $data['summaryApproved'],
            'summaryPaid' => $data['summaryPaid'],
        ]);
    }

    public function pdf(Request $request): Response
    {
        $data = $this->buildDiscountReportData($request);

        $rows = (clone $data['discountedQuery'])
            ->join('branches', 'branches.id', '=', 'sale_items.branch_id')
            ->join('locations', 'locations.id', '=', 'sale_items.location_id')
            ->join('users', 'users.id', '=', 'sale_items.user_id')
            ->select($data['rowSelect'])
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sale_items.id')
            ->get();

        return $this->streamReportPdf('reports.pdf.discounts', [
            'title' => 'Liste de remises',
            'period' => $this->formatReportPeriod($data['filters']['date_from'], $data['filters']['date_to']),
            'rows' => $rows,
            'summaryOriginal' => $data['summaryOriginal'],
            'summaryApproved' => $data['summaryApproved'],
            'summaryPaid' => $data['summaryPaid'],
        ], 'liste-remises', 'landscape');
    }

    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     discountedQuery: Builder,
     *     rowSelect: array<int, mixed>,
     *     summaryOriginal: float,
     *     summaryApproved: float,
     *     summaryPaid: float
     * }
     */
    private function buildDiscountReportData(Request $request): array
    {
        $this->authorizeFinancialReport();
        $filters = $this->resolveReportDateFilters($request);

        $baseQuery = $this->discountReportBaseQuery($filters['date_from'], $filters['date_to']);

        $approvedDiscountSql = $this->approvedDiscountSql();
        $requestedDiscountSql = $this->requestedDiscountSql();
        $lineDiscountSql = '('.$approvedDiscountSql.') + ('.$requestedDiscountSql.')';

        $discountedQuery = (clone $baseQuery)->whereRaw($lineDiscountSql.' > 0');

        $summaryApproved = (float) (clone $discountedQuery)->sum(DB::raw($approvedDiscountSql));
        $summaryRequested = (float) (clone $discountedQuery)->sum(DB::raw($requestedDiscountSql));
        $summaryPaid = (float) (clone $discountedQuery)->sum(DB::raw('sale_items.line_total'));
        $summaryOriginal = $summaryPaid + $summaryApproved + $summaryRequested;

        $rowSelect = [
            'sale_items.id',
            'sale_items.quantity',
            'products.name as product_name',
            'products.sku as product_sku',
            'sales.sold_at as movement_date',
            'branches.name as branch_name',
            'locations.name as location_name',
            'users.name as user_name',
            DB::raw('sale_items.line_total as amount_paid'),
            DB::raw('sale_items.line_total + '.$lineDiscountSql.' as original_amount'),
            DB::raw($approvedDiscountSql.' as approved_discount'),
        ];

        return compact(
            'filters',
            'discountedQuery',
            'rowSelect',
            'summaryOriginal',
            'summaryApproved',
            'summaryPaid',
        );
    }

    private function discountReportBaseQuery(string $dateFrom, string $dateTo): Builder
    {
        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereDate('sales.sold_at', '>=', $dateFrom)
            ->whereDate('sales.sold_at', '<=', $dateTo)
            ->where(function (Builder $q) {
                $q->where('sale_items.discount_amount', '>', 0)
                    ->orWhere('sales.discount_amount', '>', 0)
                    ->orWhere('sales.discount_requested_amount', '>', 0);
            });

        $this->applyBranchFilter($query, 'sale_items.branch_id');

        $user = auth()->user();
        if (! $user?->hasApplicationAdminAccess() && ! $user?->canAccessCashDeskFinanceFeatures()) {
            $query->where('sale_items.user_id', $user->id);
        }

        return $query;
    }

    private function approvedDiscountSql(): string
    {
        $pending = Sale::STATUS_PENDING_DISCOUNT;

        return 'CASE
            WHEN sales.sale_status = \''.$pending.'\' THEN 0
            ELSE COALESCE(sale_items.discount_amount, 0)
        END + CASE
            WHEN sales.sale_status != \''.$pending.'\'
                 AND COALESCE(sale_items.discount_amount, 0) = 0
                 AND COALESCE(sales.discount_amount, 0) > 0
                 AND COALESCE(sales.subtotal_amount, sales.total_amount, 0) > 0
            THEN (sale_items.line_total / COALESCE(sales.subtotal_amount, sales.total_amount)) * sales.discount_amount
            ELSE 0
        END';
    }

    private function requestedDiscountSql(): string
    {
        $pending = Sale::STATUS_PENDING_DISCOUNT;

        return 'CASE
            WHEN sales.sale_status = \''.$pending.'\' THEN COALESCE(sale_items.discount_amount, 0)
            ELSE 0
        END + CASE
            WHEN sales.sale_status = \''.$pending.'\'
                 AND COALESCE(sale_items.discount_amount, 0) = 0
                 AND COALESCE(sales.discount_requested_amount, 0) > 0
                 AND COALESCE(sales.subtotal_amount, sales.total_amount, 0) > 0
            THEN (sale_items.line_total / COALESCE(sales.subtotal_amount, sales.total_amount)) * sales.discount_requested_amount
            ELSE 0
        END';
    }
}
