<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class StockTransferController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = StockTransfer::query()
            ->with([
                'fromLocation:id,name,branch_id',
                'fromLocation.branch:id,name',
                'toLocation:id,name,branch_id',
                'toLocation.branch:id,name',
                'user:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('id');

        $this->applyStockTransferBranchFilter($query);

        $transfers = $query->paginate(20);

        return view('stock_transfers.index', compact('transfers'));
    }

    public function create(): View
    {
        $productQuery = Product::query()->with('department')->orderBy('name');
        $this->applyProductBranchScope($productQuery);
        $products = $productQuery->get();
        $locations = $this->locationsForUser();

        return view('stock_transfers.create', compact('products', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_location_id' => ['required', 'exists:locations,id'],
            'to_location_id' => ['required', 'exists:locations,id', 'different:from_location_id'],
            'transferred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->branchFilterIds() !== null) {
            $allowedLocationIds = $this->locationIdsForUser();
            foreach (['from_location_id', 'to_location_id'] as $field) {
                if (! in_array((int) $data[$field], $allowedLocationIds, true)) {
                    abort(403, 'Emplacement non autorisé.');
                }
            }
        }

        $fromId = (int) $data['from_location_id'];
        $toId = (int) $data['to_location_id'];

        $merged = [];
        foreach ($data['items'] as $row) {
            $pid = (int) $row['product_id'];
            $merged[$pid] = ($merged[$pid] ?? 0) + (int) $row['quantity'];
        }

        foreach (array_keys($merged) as $productId) {
            if ($this->branchFilterIds() !== null) {
                $productQuery = Product::query()->whereKey($productId);
                $this->applyProductBranchScope($productQuery);
                abort_unless($productQuery->exists(), 403, 'Produit non autorisé.');
            }
        }

        $occurredOn = \Carbon\Carbon::parse($data['transferred_at'])->startOfDay();

        try {
            $transfer = DB::transaction(function () use ($request, $data, $fromId, $toId, $merged, $occurredOn) {
                $transfer = StockTransfer::create([
                    'from_location_id' => $fromId,
                    'to_location_id' => $toId,
                    'transferred_at' => $occurredOn,
                    'user_id' => $request->user()->id,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($merged as $productId => $quantity) {
                    Stock::modifyQuantity($productId, $fromId, -$quantity);
                    Stock::modifyQuantity($productId, $toId, $quantity);

                    StockTransferItem::create([
                        'stock_transfer_id' => $transfer->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);

                    StockMovement::create([
                        'type' => 'transfer',
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'from_location_id' => $fromId,
                        'to_location_id' => $toId,
                        'user_id' => $request->user()->id,
                        'stock_transfer_id' => $transfer->id,
                        'notes' => null,
                        'occurred_on' => $occurredOn,
                    ]);
                }

                return $transfer;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        }

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', 'Transfert enregistré.');
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->ensureUserCanAccessStockTransfer($stockTransfer);

        $stockTransfer->load([
            'fromLocation.branch:id,name',
            'toLocation.branch:id,name',
            'items.product:id,name,sku',
            'user:id,name',
        ]);

        return view('stock_transfers.show', compact('stockTransfer'));
    }

    private function applyStockTransferBranchFilter(Builder $query): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function (Builder $q) use ($ids) {
            $q->whereHas('fromLocation', fn (Builder $l) => $l->whereIn('branch_id', $ids))
                ->orWhereHas('toLocation', fn (Builder $l) => $l->whereIn('branch_id', $ids));
        });
    }

    private function ensureUserCanAccessStockTransfer(StockTransfer $stockTransfer): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            abort(403, 'Accès non autorisé.');
        }

        $stockTransfer->loadMissing('fromLocation:id,branch_id', 'toLocation:id,branch_id');

        $fromOk = in_array((int) $stockTransfer->fromLocation->branch_id, $ids, true);
        $toOk = in_array((int) $stockTransfer->toLocation->branch_id, $ids, true);
        abort_unless($fromOk || $toOk, 403, 'Accès non autorisé pour ce transfert.');
    }
}
