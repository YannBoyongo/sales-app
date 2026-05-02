<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $locations = $this->locationsForUser();
        $locationIds = $locations->pluck('id')->all();

        $productQuery = Product::query()
            ->select(['id', 'name', 'sku', 'minimum_stock'])
            ->orderBy('name');

        $this->applyProductBranchScope($productQuery);

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

        return view('stocks.index', compact('products', 'locations', 'matrix'));
    }

    public function edit(Stock $stock): View
    {
        $stock->load(['product', 'location.branch']);
        $this->ensureUserCanAccessLocation($stock->location);

        return view('stocks.edit', compact('stock'));
    }

    public function update(Request $request, Stock $stock): RedirectResponse
    {
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

        return redirect()->route('stocks.index')->with('success', 'Seuil d’alerte enregistré.');
    }
}
