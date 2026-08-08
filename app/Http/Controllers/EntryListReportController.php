<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EntryListReportController extends Controller
{
    use RespectsUserBranch;
    use RendersReportPdf;

    public function __invoke(Request $request): View
    {
        $this->authorizeStockReport();

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        $baseQuery = $this->entryReportBaseQuery($dateFrom, $dateTo);

        $summaryQuantity = (int) (clone $baseQuery)->sum('stock_movements.quantity');

        $rows = (clone $baseQuery)
            ->select([
                'stock_movements.product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                DB::raw('MAX(COALESCE(stock_movements.occurred_on, DATE(stock_movements.created_at))) as movement_date'),
                DB::raw('SUM(stock_movements.quantity) as total_quantity'),
                DB::raw('GROUP_CONCAT(DISTINCT locations.name ORDER BY locations.name SEPARATOR ", ") as locations'),
            ])
            ->groupBy('stock_movements.product_id', 'products.name', 'products.sku')
            ->orderBy('products.name')
            ->paginate(50)
            ->withQueryString();

        $filters['date_from'] = $dateFrom;
        $filters['date_to'] = $dateTo;

        return view('reports.entries', compact('rows', 'filters', 'summaryQuantity'));
    }

    public function pdf(Request $request): Response
    {
        $this->authorizeStockReport();

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        $baseQuery = $this->entryReportBaseQuery($dateFrom, $dateTo);
        $summaryQuantity = (int) (clone $baseQuery)->sum('stock_movements.quantity');

        $rows = (clone $baseQuery)
            ->select([
                'stock_movements.product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                DB::raw('MAX(COALESCE(stock_movements.occurred_on, DATE(stock_movements.created_at))) as movement_date'),
                DB::raw('SUM(stock_movements.quantity) as total_quantity'),
                DB::raw('GROUP_CONCAT(DISTINCT locations.name ORDER BY locations.name SEPARATOR ", ") as locations'),
            ])
            ->groupBy('stock_movements.product_id', 'products.name', 'products.sku')
            ->orderBy('products.name')
            ->get();

        return $this->streamReportPdf('reports.pdf.entries', [
            'title' => 'Liste des entrées',
            'period' => $this->formatReportPeriod($dateFrom, $dateTo),
            'rows' => $rows,
            'summaryQuantity' => $summaryQuantity,
        ], 'liste-entrees');
    }

    private function authorizeStockReport(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->isAdmin() || $user?->canAccessCashDeskFinanceFeatures() || $user?->canAccessPosSales(),
            403
        );
    }

    private function entryReportBaseQuery(string $dateFrom, string $dateTo): Builder
    {
        $query = StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('locations', 'locations.id', '=', 'stock_movements.to_location_id')
            ->where('stock_movements.type', 'entry')
            ->whereRaw('COALESCE(stock_movements.occurred_on, DATE(stock_movements.created_at)) >= ?', [$dateFrom])
            ->whereRaw('COALESCE(stock_movements.occurred_on, DATE(stock_movements.created_at)) <= ?', [$dateTo]);

        $this->applyStockMovementBranchFilter($query);

        return $query;
    }

    public static function formatReportDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse($value)->translatedFormat('d/m/Y');
    }
}
