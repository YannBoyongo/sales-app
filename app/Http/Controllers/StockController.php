<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class StockController extends Controller
{
    use RespectsUserBranch;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $stockBranches = $this->stockBranchesForMatrix();
        $branchParam = $request->query('branch');
        $branchId = ($branchParam !== null && $branchParam !== '') ? (int) $branchParam : null;
        $selectedBranch = $this->resolveStockMatrixBranch($branchId, $stockBranches);

        $locations = collect();
        if ($selectedBranch !== null) {
            $locations = $this->locationsForUser()
                ->where('branch_id', $selectedBranch->id)
                ->values();
        }
        $locationIds = $locations->pluck('id')->all();

        $departmentIdQuery = Product::query()->select('products.department_id')->distinct();
        if ($selectedBranch !== null) {
            $this->applyProductScopeForBranch($departmentIdQuery, $selectedBranch);
        } else {
            $this->applyProductBranchScope($departmentIdQuery);
        }
        $departments = Department::query()
            ->whereIn('id', $departmentIdQuery->whereNotNull('department_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $productQuery = Product::query()
            ->select(['id', 'name', 'sku', 'minimum_stock', 'department_id'])
            ->orderBy('name');

        if ($selectedBranch !== null) {
            $this->applyProductScopeForBranch($productQuery, $selectedBranch);
        } else {
            $this->applyProductBranchScope($productQuery);
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            if ($departments->contains('id', $departmentId)) {
                $productQuery->where('department_id', $departmentId);
            }
        }

        $products = $productQuery->paginate(30)->withQueryString();

        $matrix = [];
        if ($products->isNotEmpty() && $locationIds !== []) {
            $stocks = Stock::query()
                ->with(['product' => static fn ($q) => $q->select('id', 'minimum_stock')])
                ->whereIn('product_id', $products->pluck('id'))
                ->whereIn('location_id', $locationIds)
                ->get();

            foreach ($stocks as $stock) {
                $matrix[$stock->product_id][$stock->location_id] = $stock;
            }
        }

        $adjustmentLocations = collect();
        $adjustmentProducts = collect();
        if (auth()->user()?->isAdmin()) {
            $adjustmentLocations = Location::query()
                ->with('branch:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'branch_id']);
            $adjustmentProducts = Product::query()
                ->select(['id', 'name', 'sku'])
                ->orderBy('name')
                ->get();
        }

        $batchesByProduct = [];
        if ($locationIds !== [] && $products->isNotEmpty()) {
            $stockBatches = StockBatch::query()
                ->whereIn('location_id', $locationIds)
                ->whereIn('product_id', $products->pluck('id'))
                ->where('quantity', '>', 0)
                ->orderBy('id')
                ->get(['product_id', 'batch_number', 'unit_cost', 'quantity']);

            foreach ($stockBatches as $batch) {
                $key = $batch->batch_number.'|'.number_format((float) $batch->unit_cost, 2, '.', '');
                if (! isset($batchesByProduct[$batch->product_id][$key])) {
                    $batchesByProduct[$batch->product_id][$key] = [
                        'batch_number' => $batch->batch_number,
                        'unit_cost' => round((float) $batch->unit_cost, 2),
                        'quantity' => 0,
                    ];
                }
                $batchesByProduct[$batch->product_id][$key]['quantity'] += (int) $batch->quantity;
            }

            foreach ($batchesByProduct as $productId => $layers) {
                $batchesByProduct[$productId] = array_values($layers);
            }
        }

        return view('stocks.index', compact(
            'products',
            'locations',
            'matrix',
            'adjustmentLocations',
            'adjustmentProducts',
            'stockBranches',
            'selectedBranch',
            'departments',
            'filters',
            'batchesByProduct',
        ));
    }

    public function valuation(Request $request): View
    {
        $filters = $request->validate([
            'branch' => ['nullable'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $stockBranches = $this->stockBranchesForMatrix();
        $branchParam = $request->query('branch');
        $branchId = ($branchParam !== null && $branchParam !== '') ? (int) $branchParam : null;
        $showAllBranches = $branchId === null;
        $selectedBranch = $showAllBranches
            ? null
            : $this->resolveStockMatrixBranch($branchId, $stockBranches);

        $locations = $this->locationsForUser();
        if ($selectedBranch !== null) {
            $locations = $locations->where('branch_id', $selectedBranch->id)->values();
        }

        $departmentIdQuery = Product::query()->select('products.department_id')->distinct();
        if ($selectedBranch !== null) {
            $this->applyProductScopeForBranch($departmentIdQuery, $selectedBranch);
        } else {
            $this->applyProductBranchScope($departmentIdQuery);
        }
        $departments = Department::query()
            ->whereIn('id', $departmentIdQuery->whereNotNull('department_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = $this->valuationBatchQuery($filters, $selectedBranch, $locations, $departments);

        $aggregateByProduct = $showAllBranches && empty($filters['location_id']);

        $summaryRow = (clone $query)
            ->select(
                DB::raw('COALESCE(SUM(stock_batches.quantity), 0) as total_qty'),
                DB::raw('COALESCE(SUM(stock_batches.quantity * stock_batches.unit_cost), 0) as total_value'),
                DB::raw('COALESCE(SUM(CASE WHEN products.unit_price IS NOT NULL THEN stock_batches.quantity * products.unit_price ELSE 0 END), 0) as total_sales_value'),
            )
            ->first();

        $untrackedQty = $this->untrackedStockQuantityForValuation(
            $locations->pluck('id')->all(),
            $filters,
            $departments,
        );

        $valuationLineSelect = [
            'stock_batches.product_id',
            DB::raw('SUM(stock_batches.quantity) as quantity'),
            DB::raw('SUM(stock_batches.quantity * stock_batches.unit_cost) as total_value'),
            DB::raw('ROUND(SUM(stock_batches.quantity * stock_batches.unit_cost) / SUM(stock_batches.quantity), 2) as weighted_unit_cost'),
        ];

        $valuationLinesQuery = (clone $query)->select($valuationLineSelect);

        if ($aggregateByProduct) {
            $valuationLinesQuery
                ->groupBy('stock_batches.product_id')
                ->orderBy('products.name');
        } else {
            $valuationLinesQuery
                ->addSelect('stock_batches.location_id')
                ->groupBy('stock_batches.product_id', 'stock_batches.location_id')
                ->orderBy('products.name')
                ->orderBy('stock_batches.location_id');
        }

        $valuationLines = $valuationLinesQuery
            ->paginate(50)
            ->withQueryString();

        $lineItems = collect($valuationLines->items());
        $productsById = Product::query()
            ->with('department:id,name')
            ->whereIn('id', $lineItems->pluck('product_id')->unique()->filter())
            ->get(['id', 'name', 'sku', 'department_id', 'unit_price'])
            ->keyBy('id');

        $batchesByLine = [];
        if ($lineItems->isNotEmpty()) {
            $batchRowsQuery = (clone $query)
                ->select(
                    'stock_batches.product_id',
                    'stock_batches.location_id',
                    'stock_batches.batch_number',
                    'stock_batches.unit_cost',
                    'stock_batches.quantity',
                )
                ->orderBy('stock_batches.location_id')
                ->orderBy('stock_batches.batch_number')
                ->orderBy('stock_batches.unit_cost');

            if ($aggregateByProduct) {
                $batchRowsQuery->join('locations', 'locations.id', '=', 'stock_batches.location_id')
                    ->addSelect('locations.name as location_name');
            }

            $batchRows = $batchRowsQuery->get();

            foreach ($batchRows as $batch) {
                $key = $aggregateByProduct
                    ? (string) $batch->product_id
                    : $batch->product_id.'-'.$batch->location_id;
                $entry = [
                    'batch_number' => $batch->batch_number,
                    'unit_cost' => round((float) $batch->unit_cost, 2),
                    'quantity' => (int) $batch->quantity,
                ];
                if ($aggregateByProduct) {
                    $entry['location_name'] = $batch->location_name;
                }
                $batchesByLine[$key][] = $entry;
            }
        }

        return view('stocks.valuation', compact(
            'valuationLines',
            'productsById',
            'batchesByLine',
            'locations',
            'departments',
            'stockBranches',
            'selectedBranch',
            'filters',
            'summaryRow',
            'untrackedQty',
            'showAllBranches',
            'aggregateByProduct',
        ));
    }

    public function productLedger(Request $request, Product $product): View
    {
        $filters = $request->validate([
            'branch' => ['nullable'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $productQuery = Product::query()
            ->with('department:id,name')
            ->whereKey($product->id);
        $this->applyProductBranchScope($productQuery);
        $product = $productQuery->firstOrFail();

        $stockBranches = $this->stockBranchesForMatrix();
        $branchParam = $request->query('branch');
        $branchId = ($branchParam !== null && $branchParam !== '') ? (int) $branchParam : null;
        $showAllBranches = $branchId === null;
        $selectedBranch = $showAllBranches
            ? null
            : $this->resolveStockMatrixBranch($branchId, $stockBranches);

        $locations = $this->locationsForUser();
        if ($selectedBranch !== null) {
            $locations = $locations->where('branch_id', $selectedBranch->id)->values();
        }

        $locationScopeId = null;
        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            if ($locations->contains('id', $locationId)) {
                $locationScopeId = $locationId;
            }
        }

        $scopedLocationIds = $locationScopeId !== null
            ? [$locationScopeId]
            : $locations->pluck('id')->all();

        $selectedLocation = $locationScopeId !== null
            ? $locations->firstWhere('id', $locationScopeId)
            : null;

        $currentStock = $scopedLocationIds === []
            ? 0
            : (int) Stock::query()
                ->where('product_id', $product->id)
                ->whereIn('location_id', $scopedLocationIds)
                ->sum('quantity');

        $movementsQuery = StockMovement::query()
            ->with([
                'fromLocation:id,name',
                'toLocation:id,name',
            ])
            ->where('product_id', $product->id);

        $this->applyStockMovementBranchFilter($movementsQuery);

        if ($scopedLocationIds !== []) {
            $movementsQuery->where(function ($query) use ($scopedLocationIds) {
                $query->whereIn('from_location_id', $scopedLocationIds)
                    ->orWhereIn('to_location_id', $scopedLocationIds);
            });
        } else {
            $movementsQuery->whereRaw('1 = 0');
        }

        $movements = $movementsQuery
            ->orderByRaw('COALESCE(occurred_on, DATE(created_at)) ASC')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $movementRows = [];
        $totalEntry = 0;
        $totalExit = 0;

        foreach ($movements as $movement) {
            $row = $this->movementToLedgerRow($movement, $locationScopeId);
            if ($row === null) {
                continue;
            }

            $totalEntry += $row['entry'];
            $totalExit += $row['exit'];
            $movementRows[] = $row;
        }

        $openingBalance = $currentStock - $totalEntry + $totalExit;
        $runningBalance = $openingBalance;

        $ledgerRows = [[
            'date' => null,
            'type' => null,
            'label' => 'Report (solde initial)',
            'entry' => null,
            'exit' => null,
            'stock' => $openingBalance,
            'is_opening' => true,
        ]];

        foreach ($movementRows as $row) {
            $runningBalance += $row['entry'] - $row['exit'];
            $ledgerRows[] = [
                'date' => $row['date'],
                'type' => $row['type'],
                'label' => $row['label'],
                'entry' => $row['entry'] > 0 ? $row['entry'] : null,
                'exit' => $row['exit'] > 0 ? $row['exit'] : null,
                'stock' => $runningBalance,
                'is_opening' => false,
            ];
        }

        $valuationBackParams = array_filter([
            'branch' => $showAllBranches ? '' : $selectedBranch?->id,
            'location_id' => $locationScopeId,
        ], static fn ($value) => $value !== null && $value !== '');

        return view('stocks.product-ledger', compact(
            'product',
            'ledgerRows',
            'currentStock',
            'selectedBranch',
            'selectedLocation',
            'showAllBranches',
            'valuationBackParams',
        ));
    }

    /**
     * @return array{date: Carbon, type: string, label: string, entry: int, exit: int}|null
     */
    private function movementToLedgerRow(StockMovement $movement, ?int $locationScopeId): ?array
    {
        $date = $movement->occurred_on ?? $movement->created_at;
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        $entry = 0;
        $exit = 0;
        $label = '';

        switch ($movement->type) {
            case 'entry':
                if ($locationScopeId !== null && (int) $movement->to_location_id !== $locationScopeId) {
                    return null;
                }
                $entry = (int) $movement->quantity;
                $label = $movement->notes ?: ($movement->toLocation?->name ?? 'Entrée');
                break;

            case 'exit':
                if ($locationScopeId !== null && (int) $movement->from_location_id !== $locationScopeId) {
                    return null;
                }
                $exit = (int) $movement->quantity;
                $label = $movement->notes ?: ($movement->fromLocation?->name ?? 'Sortie');
                break;

            case 'transfer':
                $fromId = (int) $movement->from_location_id;
                $toId = (int) $movement->to_location_id;

                if ($locationScopeId !== null) {
                    if ($toId === $locationScopeId) {
                        $entry = (int) $movement->quantity;
                        $label = $movement->fromLocation?->name ?? 'Transfert entrant';
                    } elseif ($fromId === $locationScopeId) {
                        $exit = (int) $movement->quantity;
                        $label = $movement->toLocation?->name ?? 'Transfert sortant';
                    } else {
                        return null;
                    }
                } else {
                    $label = trim(($movement->fromLocation?->name ?? '?').' - '.($movement->toLocation?->name ?? '?'));
                    if ($movement->stock_transfer_id) {
                        $label .= ' (Transfert #'.$movement->stock_transfer_id.')';
                    }
                }
                break;

            case 'adjustment':
                if ($locationScopeId !== null && (int) $movement->from_location_id !== $locationScopeId) {
                    return null;
                }

                $delta = $this->adjustmentDeltaFromMovement($movement);
                if ($delta > 0) {
                    $entry = $delta;
                } elseif ($delta < 0) {
                    $exit = abs($delta);
                }

                $label = $movement->notes
                    ? (string) str($movement->notes)->before(' - ')->before(' — ')
                    : 'Inventaire';

                if ($locationScopeId === null && $movement->fromLocation) {
                    $label .= ' - '.$movement->fromLocation->name;
                }
                break;

            default:
                return null;
        }

        return [
            'date' => $date,
            'type' => $this->ledgerTypeLabel($movement),
            'label' => $this->normalizeLedgerLabel($label),
            'entry' => $entry,
            'exit' => $exit,
        ];
    }

    private function ledgerTypeLabel(StockMovement $movement): string
    {
        if ($movement->type === 'exit') {
            return $movement->sale_item_id ? 'Vente' : 'Sortie';
        }

        if ($movement->type === 'entry') {
            if ($movement->purchase_order_reception_id) {
                return 'Réception';
            }

            $notes = $movement->notes ?? '';
            if (str_starts_with($notes, 'Réception PO')) {
                return 'Réception';
            }

            return 'Entrée';
        }

        if ($movement->type === 'transfer') {
            return 'Transfert';
        }

        if ($movement->type === 'adjustment') {
            return 'Inventaire';
        }

        return ucfirst($movement->type);
    }

    private function normalizeLedgerLabel(string $label): string
    {
        $label = str_replace(['—', '–', '→', '·'], '-', $label);
        $normalized = preg_replace('/\s*-\s*/', ' - ', trim($label));

        return $normalized ?? trim($label);
    }

    private function adjustmentDeltaFromMovement(StockMovement $movement): int
    {
        if (preg_match('/Ajustement inventaire : (\d+)\s*[→\-]\s*(\d+)/u', $movement->notes ?? '', $matches)) {
            return (int) $matches[2] - (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  \Illuminate\Support\Collection<int, \App\Models\Location>  $locations
     * @param  \Illuminate\Support\Collection<int, \App\Models\Department>  $departments
     */
    private function valuationBatchQuery(
        array $filters,
        ?\App\Models\Branch $selectedBranch,
        $locations,
        $departments,
    ) {
        $query = StockBatch::query()
            ->join('products', 'products.id', '=', 'stock_batches.product_id')
            ->where('stock_batches.quantity', '>', 0)
            ->whereHas('location');

        $this->applyStockBranchFilter($query);

        if ($selectedBranch !== null) {
            $query->whereHas('location', fn ($q) => $q->where('branch_id', $selectedBranch->id));
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            if ($locations->contains('id', $locationId)) {
                $query->where('stock_batches.location_id', $locationId);
            }
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            if ($departments->contains('id', $departmentId)) {
                $query->where('products.department_id', $departmentId);
            }
        }

        return $query;
    }

    /**
     * @param  array<int, int|string|null>  $filters
     * @param  \Illuminate\Support\Collection<int, \App\Models\Department>|iterable  $departments
     */
    private function untrackedStockQuantityForValuation(
        array $locationIds,
        array $filters,
        $departments,
    ): int {
        if ($locationIds === []) {
            return 0;
        }

        $stockQuery = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereIn('stocks.location_id', $locationIds);

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            if (in_array($locationId, $locationIds, true)) {
                $stockQuery->where('stocks.location_id', $locationId);
            }
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            if ($departments->contains('id', $departmentId)) {
                $stockQuery->where('products.department_id', $departmentId);
            }
        }

        $batchTotals = StockBatch::query()
            ->selectRaw('product_id, location_id, SUM(quantity) as batched_qty')
            ->where('quantity', '>', 0)
            ->whereIn('location_id', $locationIds)
            ->groupBy('product_id', 'location_id');

        $row = $stockQuery
            ->leftJoinSub($batchTotals, 'batch_totals', function ($join) {
                $join->on('batch_totals.product_id', '=', 'stocks.product_id')
                    ->on('batch_totals.location_id', '=', 'stocks.location_id');
            })
            ->selectRaw('COALESCE(SUM(GREATEST(stocks.quantity - COALESCE(batch_totals.batched_qty, 0), 0)), 0) as untracked_qty')
            ->value('untracked_qty');

        return (int) $row;
    }

    public function currentQuantity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        $qty = (int) (Stock::query()
            ->where('product_id', $data['product_id'])
            ->where('location_id', $data['location_id'])
            ->value('quantity') ?? 0);

        return response()->json(['quantity' => $qty]);
    }

    public function applyAdjustment(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $productId = (int) $data['product_id'];
        $locationId = (int) $data['location_id'];
        $newQty = (int) $data['quantity'];
        $wantsJson = $request->expectsJson();

        try {
            $result = DB::transaction(function () use ($request, $data, $productId, $locationId, $newQty) {
                $stock = Stock::query()
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->lockForUpdate()
                    ->first();

                $oldQty = $stock?->quantity ?? 0;
                $delta = $newQty - $oldQty;

                if ($delta === 0) {
                    $stock = $stock ?? Stock::query()
                        ->with(['product:id,minimum_stock'])
                        ->where('product_id', $productId)
                        ->where('location_id', $locationId)
                        ->first();

                    return [
                        'adjusted' => false,
                        'quantity' => $oldQty,
                        'below_minimum' => $this->stockCellBelowMinimum($stock, $productId),
                    ];
                }

                Stock::modifyQuantity($productId, $locationId, $delta);

                $noteSuffix = isset($data['notes']) && $data['notes'] !== ''
                    ? ' - '.$data['notes']
                    : '';

                StockMovement::create([
                    'type' => 'adjustment',
                    'product_id' => $productId,
                    'quantity' => abs($delta),
                    'from_location_id' => $locationId,
                    'to_location_id' => null,
                    'user_id' => $request->user()->id,
                    'notes' => 'Ajustement inventaire : '.$oldQty.' - '.$newQty.$noteSuffix,
                ]);

                $stock = Stock::query()
                    ->with(['product:id,minimum_stock'])
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->first();

                return [
                    'adjusted' => true,
                    'quantity' => $newQty,
                    'below_minimum' => $this->stockCellBelowMinimum($stock, $productId),
                ];
            });
        } catch (RuntimeException $e) {
            if ($wantsJson) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('stocks.index', $this->stockIndexRedirectParams($request))
                ->withErrors(['adjustment' => $e->getMessage()]);
        }

        if ($wantsJson) {
            return response()->json($result);
        }

        if (! $result['adjusted']) {
            return redirect()
                ->route('stocks.index', $this->stockIndexRedirectParams($request))
                ->with('warning', 'Aucun changement : la quantité saisie est déjà celle en base.');
        }

        return redirect()
            ->route('stocks.index', $this->stockIndexRedirectParams($request))
            ->with('success', 'Stock mis à jour et mouvement d’ajustement enregistré.');
    }

    private function stockCellBelowMinimum(?Stock $stock, int $productId): bool
    {
        if ($stock !== null) {
            return $stock->isBelowMinimum();
        }

        $productMin = Product::query()->whereKey($productId)->value('minimum_stock');

        return $productMin !== null && 0 < (int) $productMin;
    }

    public function edit(Request $request, Stock $stock): View
    {
        abort_unless(! auth()->user()?->isInventoryReadOnly(), 403);

        $stock->load(['product', 'location.branch']);
        $this->ensureUserCanAccessLocation($stock->location);

        $stocksIndexQuery = $request->only(['branch', 'department_id']);

        return view('stocks.edit', compact('stock', 'stocksIndexQuery'));
    }

    public function update(Request $request, Stock $stock): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $this->ensureUserCanAccessLocation($stock->location);

        $raw = $request->input('minimum_stock');
        if ($raw === '' || $raw === null) {
            $request->merge(['minimum_stock' => null]);
        }

        $data = $request->validate([
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $stock->update([
            'minimum_stock' => $data['minimum_stock'] ?? null,
        ]);

        return redirect()
            ->route('stocks.index', $this->stockIndexRedirectParams($request))
            ->with('success', 'Seuil d’alerte enregistré.');
    }

    /**
     * @return array<string, mixed>
     */
    private function stockIndexRedirectParams(Request $request): array
    {
        $params = [];

        if ($request->filled('branch')) {
            $params['branch'] = $request->input('branch');
        }

        if ($request->filled('department_id')) {
            $params['department_id'] = $request->input('department_id');
        }

        return $params;
    }
}
