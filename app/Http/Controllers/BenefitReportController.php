<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Models\Product;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BenefitReportController extends Controller
{
    use RespectsUserBranch;
    use RendersReportPdf;

    public function __invoke(Request $request): View
    {
        $built = $this->buildBenefitReportQuery($request);
        $query = $built['query'];
        $summary = $built['summary'];
        $dateFrom = $built['dateFrom'];
        $dateTo = $built['dateTo'];
        $filters = $built['filters'];
        $costScope = $built['costScope'];

        $items = (clone $query)
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sale_items.id')
            ->select('sale_items.*')
            ->paginate(40)
            ->withQueryString();

        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('reports.benefits', [
            'items' => $items,
            'products' => $products,
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'product_id' => $filters['product_id'] ?? null,
                'cost_scope' => $costScope,
            ],
        ]);
    }

    public function pdf(Request $request): Response
    {
        ['query' => $query, 'summary' => $summary, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo] = $this->buildBenefitReportQuery($request);

        $items = (clone $query)
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sale_items.id')
            ->select('sale_items.*')
            ->get();

        $summaryHtml = '<p>Lignes : '.number_format($summary['lines']).' · Quantité : '.number_format($summary['quantity'])
            .' · Ventes : '.\App\Support\Money::usd($summary['revenue'])
            .' · Coût : '.\App\Support\Money::usd($summary['cost'])
            .' · Bénéfice : '.\App\Support\Money::usd($summary['benefit']).'</p>';

        return $this->streamReportPdf('reports.pdf.benefits', [
            'title' => 'Bénéfices par article',
            'period' => $this->formatReportPeriod($dateFrom, $dateTo),
            'summaryHtml' => $summaryHtml,
            'items' => $items,
        ], 'benefices-par-article', 'landscape');
    }

    /**
     * @return array{query: \Illuminate\Database\Eloquent\Builder, summary: array<string, float|int>, dateFrom: string, dateTo: string}
     */
    private function buildBenefitReportQuery(Request $request): array
    {
        $user = $request->user();
        abort_unless(
            $user?->hasApplicationAdminAccess() || $user?->canAccessCashDeskFinanceFeatures() || $user?->canAccessPosSales(),
            403
        );

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'cost_scope' => ['nullable', 'in:all,with_cost,without_cost'],
        ]);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();
        $costScope = $filters['cost_scope'] ?? 'all';

        $query = SaleItem::query()
            ->with([
                'product:id,name,sku',
                'sale:id,reference,sold_at,branch_id',
                'branch:id,name',
                'location:id,name',
            ])
            ->whereHas('sale', function ($q) use ($dateFrom, $dateTo) {
                $q->whereDate('sold_at', '>=', $dateFrom)
                    ->whereDate('sold_at', '<=', $dateTo);
            });

        $this->applyBranchFilter($query, 'branch_id');

        if (! $user?->hasApplicationAdminAccess() && ! $user?->canAccessCashDeskFinanceFeatures()) {
            $query->where('user_id', $user->id);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if ($costScope === 'with_cost') {
            $query->whereNotNull('unit_cost');
        } elseif ($costScope === 'without_cost') {
            $query->whereNull('unit_cost');
        }

        $summaryQuery = (clone $query)->toBase();
        $summary = [
            'lines' => (int) (clone $summaryQuery)->count(),
            'quantity' => (int) (clone $summaryQuery)->sum('quantity'),
            'revenue' => (float) (clone $summaryQuery)->sum(DB::raw('line_total - COALESCE(discount_amount, 0)')),
            'cost' => (float) (clone $summaryQuery)->whereNotNull('cost_total')->sum('cost_total'),
            'benefit' => (float) (clone $summaryQuery)->whereNotNull('benefit')->sum('benefit'),
            'unknown_cost_lines' => (int) (clone $summaryQuery)->whereNull('unit_cost')->count(),
        ];

        return [
            'query' => $query,
            'summary' => $summary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filters' => $filters,
            'costScope' => $costScope,
        ];
    }
}
