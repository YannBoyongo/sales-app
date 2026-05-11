<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
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

        $productQuery = Product::query()
            ->select(['id', 'name', 'sku', 'minimum_stock'])
            ->orderBy('name');

        if ($selectedBranch !== null) {
            $this->applyProductScopeForBranch($productQuery, $selectedBranch);
        } else {
            $this->applyProductBranchScope($productQuery);
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

    public function applyAdjustment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $adjusted = false;

        try {
            DB::transaction(function () use ($request, $data, &$adjusted) {
                $productId = (int) $data['product_id'];
                $locationId = (int) $data['location_id'];
                $newQty = (int) $data['quantity'];

                $stock = Stock::query()
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->lockForUpdate()
                    ->first();

                $oldQty = $stock?->quantity ?? 0;
                $delta = $newQty - $oldQty;

                if ($delta === 0) {
                    return;
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

                $adjusted = true;
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('stocks.index', $this->stockIndexBranchRedirectParams($request))
                ->withErrors(['adjustment' => $e->getMessage()]);
        }

        if (! $adjusted) {
            return redirect()
                ->route('stocks.index', $this->stockIndexBranchRedirectParams($request))
                ->with('warning', 'Aucun changement : la quantité saisie est déjà celle en base.');
        }

        return redirect()
            ->route('stocks.index', $this->stockIndexBranchRedirectParams($request))
            ->with('success', 'Stock mis à jour et mouvement d’ajustement enregistré.');
    }

    public function edit(Request $request, Stock $stock): View
    {
        abort_unless(! auth()->user()?->isInventoryReadOnly(), 403);

        $stock->load(['product', 'location.branch']);
        $this->ensureUserCanAccessLocation($stock->location);

        $stocksIndexQuery = $request->only('branch');

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
            ->route('stocks.index', $this->stockIndexBranchRedirectParams($request))
            ->with('success', 'Seuil d’alerte enregistré.');
    }

    /**
     * @return array<string, mixed>
     */
    private function stockIndexBranchRedirectParams(Request $request): array
    {
        if (! $request->filled('branch')) {
            return [];
        }

        return ['branch' => $request->input('branch')];
    }
}
