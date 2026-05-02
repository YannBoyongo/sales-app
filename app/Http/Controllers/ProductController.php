<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = Product::query()
            ->with('department')
            ->orderBy('name');
        $this->applyProductBranchScope($query);

        $products = $query->paginate(20);

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        $departments = Department::orderBy('name')->get();

        return view('products.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        Product::create($data);

        return redirect()->route('products.index')->with('success', 'Produit créé.');
    }

    public function edit(Product $product): View
    {
        $this->ensureProductAccessibleForBranchUser($product);

        $departments = Department::orderBy('name')->get();

        return view('products.edit', compact('product', 'departments'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->ensureProductAccessibleForBranchUser($product);

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $product->update($data);

        return redirect()->route('products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->ensureProductAccessibleForBranchUser($product);

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produit supprimé.');
    }
}
