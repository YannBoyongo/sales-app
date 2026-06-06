<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ));
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
                    ? ' — '.$data['notes']
                    : '';

                StockMovement::create([
                    'type' => 'adjustment',
                    'product_id' => $productId,
                    'quantity' => abs($delta),
                    'from_location_id' => $locationId,
                    'to_location_id' => null,
                    'user_id' => $request->user()->id,
                    'notes' => 'Ajustement inventaire : '.$oldQty.' → '.$newQty.$noteSuffix,
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
