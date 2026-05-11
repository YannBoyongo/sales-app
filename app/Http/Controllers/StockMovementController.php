<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class StockMovementController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = StockMovement::query()
            ->with([
                'product:id,name,sku',
                'fromLocation:id,name,branch_id',
                'fromLocation.branch:id,name',
                'toLocation:id,name,branch_id',
                'toLocation.branch:id,name',
                'user:id,name',
            ])
            ->orderByDesc('id');

        $this->applyStockMovementBranchFilter($query);

        $movements = $query->paginate(25);

        return view('stock_movements.index', compact('movements'));
    }

    public function create(): View
    {
        abort_unless(! auth()->user()?->isInventoryReadOnly(), 403);

        $productQuery = Product::query()->with('department')->orderBy('name');
        $this->applyProductBranchScope($productQuery);
        $products = $productQuery->get();
        $locations = $this->locationsForUser();

        return view('stock_movements.create', compact('products', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $data = $request->validate([
            'type' => ['required', 'in:entry,exit,transfer'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'from_location_id' => ['nullable', 'exists:locations,id'],
            'to_location_id' => ['nullable', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $allowedLocationIds = $this->locationIdsForUser();
        if ($this->branchFilterIds() !== null) {
            foreach (['from_location_id', 'to_location_id'] as $field) {
                if (! empty($data[$field]) && ! in_array((int) $data[$field], $allowedLocationIds, true)) {
                    abort(403, 'Emplacement non autorisé.');
                }
            }

            $productQuery = Product::query()->whereKey((int) $data['product_id']);
            $this->applyProductBranchScope($productQuery);
            $productInBranchScope = $productQuery->exists();

            $entryIntoOwnLocation = $data['type'] === 'entry'
                && ! empty($data['to_location_id'])
                && in_array((int) $data['to_location_id'], $allowedLocationIds, true);

            abort_unless($productInBranchScope || $entryIntoOwnLocation, 403, 'Produit non autorisé.');
        }

        try {
            DB::transaction(function () use ($request, $data) {
                $userId = $request->user()->id;

                match ($data['type']) {
                    'entry' => $this->applyEntry($data, $userId),
                    'exit' => $this->applyExit($data, $userId),
                    'transfer' => $this->applyTransfer($data, $userId),
                };
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        }

        return redirect()->route('stock-movements.index')->with('success', 'Mouvement de stock enregistré.');
    }

    private function applyEntry(array $data, int $userId): void
    {
        if (empty($data['to_location_id'])) {
            throw new RuntimeException('L’emplacement de destination est requis pour une entrée.');
        }

        Stock::modifyQuantity((int) $data['product_id'], (int) $data['to_location_id'], (int) $data['quantity']);

        StockMovement::create([
            'type' => 'entry',
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'from_location_id' => null,
            'to_location_id' => $data['to_location_id'],
            'user_id' => $userId,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function applyExit(array $data, int $userId): void
    {
        if (empty($data['from_location_id'])) {
            throw new RuntimeException('L’emplacement source est requis pour une sortie.');
        }

        Stock::modifyQuantity((int) $data['product_id'], (int) $data['from_location_id'], -(int) $data['quantity']);

        StockMovement::create([
            'type' => 'exit',
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'from_location_id' => $data['from_location_id'],
            'to_location_id' => null,
            'user_id' => $userId,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function applyTransfer(array $data, int $userId): void
    {
        if (empty($data['from_location_id']) || empty($data['to_location_id'])) {
            throw new RuntimeException('Les emplacements source et destination sont requis pour un transfert.');
        }

        if ((int) $data['from_location_id'] === (int) $data['to_location_id']) {
            throw new RuntimeException('Les emplacements doivent être différents.');
        }

        Stock::modifyQuantity((int) $data['product_id'], (int) $data['from_location_id'], -(int) $data['quantity']);
        Stock::modifyQuantity((int) $data['product_id'], (int) $data['to_location_id'], (int) $data['quantity']);

        StockMovement::create([
            'type' => 'transfer',
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'from_location_id' => $data['from_location_id'],
            'to_location_id' => $data['to_location_id'],
            'user_id' => $userId,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
