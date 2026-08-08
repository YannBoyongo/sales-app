<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransferListReportController extends Controller
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

        $baseQuery = $this->transferReportBaseQuery($dateFrom, $dateTo);

        $summaryQuantity = (int) (clone $baseQuery)->sum('stock_transfer_items.quantity');

        $rows = (clone $baseQuery)
            ->select([
                'stock_transfer_items.product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                DB::raw('MAX(stock_transfers.transferred_at) as movement_date'),
                DB::raw('SUM(stock_transfer_items.quantity) as total_quantity'),
                DB::raw('GROUP_CONCAT(DISTINCT stock_transfers.transfer_scope ORDER BY stock_transfers.transfer_scope SEPARATOR ",") as transfer_scopes'),
                DB::raw('GROUP_CONCAT(DISTINCT from_locations.name ORDER BY from_locations.name SEPARATOR ", ") as origins'),
                DB::raw('GROUP_CONCAT(DISTINCT to_locations.name ORDER BY to_locations.name SEPARATOR ", ") as destinations'),
            ])
            ->groupBy('stock_transfer_items.product_id', 'products.name', 'products.sku')
            ->orderBy('products.name')
            ->paginate(50)
            ->withQueryString();

        $filters['date_from'] = $dateFrom;
        $filters['date_to'] = $dateTo;

        return view('reports.transfers', compact('rows', 'filters', 'summaryQuantity'));
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

        $baseQuery = $this->transferReportBaseQuery($dateFrom, $dateTo);
        $summaryQuantity = (int) (clone $baseQuery)->sum('stock_transfer_items.quantity');

        $rows = (clone $baseQuery)
            ->select([
                'stock_transfer_items.product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                DB::raw('MAX(stock_transfers.transferred_at) as movement_date'),
                DB::raw('SUM(stock_transfer_items.quantity) as total_quantity'),
                DB::raw('GROUP_CONCAT(DISTINCT stock_transfers.transfer_scope ORDER BY stock_transfers.transfer_scope SEPARATOR ",") as transfer_scopes'),
                DB::raw('GROUP_CONCAT(DISTINCT from_locations.name ORDER BY from_locations.name SEPARATOR ", ") as origins'),
                DB::raw('GROUP_CONCAT(DISTINCT to_locations.name ORDER BY to_locations.name SEPARATOR ", ") as destinations'),
            ])
            ->groupBy('stock_transfer_items.product_id', 'products.name', 'products.sku')
            ->orderBy('products.name')
            ->get();

        return $this->streamReportPdf('reports.pdf.transfers', [
            'title' => 'Liste de transferts',
            'period' => $this->formatReportPeriod($dateFrom, $dateTo),
            'rows' => $rows,
            'summaryQuantity' => $summaryQuantity,
        ], 'liste-transferts', 'landscape');
    }

    private function authorizeStockReport(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->isAdmin() || $user?->canAccessCashDeskFinanceFeatures() || $user?->canAccessPosSales(),
            403
        );
    }

    private function transferReportBaseQuery(string $dateFrom, string $dateTo): Builder
    {
        $query = StockTransferItem::query()
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
            ->join('locations as from_locations', 'from_locations.id', '=', 'stock_transfers.from_location_id')
            ->join('locations as to_locations', 'to_locations.id', '=', 'stock_transfers.to_location_id')
            ->where('stock_transfers.status', StockTransfer::STATUS_CONFIRMED)
            ->whereDate('stock_transfers.transferred_at', '>=', $dateFrom)
            ->whereDate('stock_transfers.transferred_at', '<=', $dateTo);

        $this->applyTransferReportBranchFilter($query);

        return $query;
    }

    private function applyTransferReportBranchFilter(Builder $query): void
    {
        $managed = $this->managedLocationIdsForUser();
        if ($managed !== null) {
            if ($managed === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where(function (Builder $q) use ($managed) {
                $q->whereIn('stock_transfers.from_location_id', $managed)
                    ->orWhereIn('stock_transfers.to_location_id', $managed);
            });

            return;
        }

        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('from_locations.branch_id', $ids)
                ->orWhereIn('to_locations.branch_id', $ids);
        });
    }

    public static function formatTransferScope(?string $scopes): string
    {
        $parts = collect(explode(',', (string) $scopes))
            ->map(fn ($scope) => trim($scope))
            ->filter()
            ->unique()
            ->values();

        if ($parts->isEmpty()) {
            return '-';
        }

        if ($parts->count() > 1) {
            return 'Mixte';
        }

        return StockTransfer::scopeLabel($parts->first());
    }

    public static function formatReportDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse($value)->translatedFormat('d/m/Y');
    }
}
