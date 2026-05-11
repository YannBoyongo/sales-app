<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class StockTransferController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        abort_unless(auth()->user()?->canViewStockTransfers(), 403);

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
        abort_unless(auth()->user()?->canManageStockTransfers(), 403);

        $productQuery = Product::query()->with('department')->orderBy('name');
        $this->applyProductBranchScope($productQuery);
        $products = $productQuery->get();

        $warehouseKinds = [Location::KIND_MAIN, Location::KIND_STORAGE];

        $picksBranchForTransfer = $this->branchFilterIds() === null;

        $branchesForTransfer = Branch::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])
            ->values()
            ->all();

        $userBranchName = null;
        if (! $picksBranchForTransfer) {
            $userBranchName = auth()->user()->branch?->name
                ?? $this->branchesForUser()->first()?->name;
        }

        $internalFromQuery = Location::query()
            ->with('branch:id,name')
            ->whereIn('kind', $warehouseKinds)
            ->orderBy('name');
        if (! $picksBranchForTransfer) {
            $this->applyBranchFilter($internalFromQuery, 'branch_id');
        }
        $internalFromLocations = $this->mapLocationOptions($internalFromQuery->get());

        $internalToQuery = Location::query()
            ->with('branch:id,name')
            ->where('kind', Location::KIND_POINT_OF_SALE)
            ->orderBy('name');
        if (! $picksBranchForTransfer) {
            $this->applyBranchFilter($internalToQuery, 'branch_id');
        }
        $internalToLocations = $this->mapLocationOptions($internalToQuery->get());

        $externalPickMode = 'split';
        $externalLocations = [];
        $externalFromLocations = [];
        $externalToLocations = [];

        if ($this->branchFilterIds() === null) {
            $externalPickMode = 'single_list';
            $externalQuery = Location::query()
                ->with('branch:id,name')
                ->whereIn('kind', $warehouseKinds)
                ->orderBy('name');
            $externalLocations = $this->mapLocationOptions($externalQuery->get());
        } else {
            $extFromQ = Location::query()
                ->with('branch:id,name')
                ->whereIn('kind', $warehouseKinds)
                ->orderBy('name');
            $this->applyBranchFilter($extFromQ, 'branch_id');
            $externalFromLocations = $this->mapLocationOptions($extFromQ->get());

            $branchIds = $this->branchFilterIds();
            $extToQ = Location::query()
                ->with('branch:id,name')
                ->whereIn('kind', $warehouseKinds)
                ->whereNotIn('branch_id', $branchIds)
                ->orderBy('name');
            $externalToLocations = $this->mapLocationOptions($extToQ->get());
        }

        $canExternal = $this->branchFilterIds() === null
            ? Branch::query()->count() >= 2
            : (count($externalFromLocations) > 0 && count($externalToLocations) > 0);

        return view('stock_transfers.create', compact(
            'products',
            'picksBranchForTransfer',
            'branchesForTransfer',
            'userBranchName',
            'internalFromLocations',
            'internalToLocations',
            'externalPickMode',
            'externalLocations',
            'externalFromLocations',
            'externalToLocations',
            'canExternal',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageStockTransfers(), 403);

        $data = $request->validate([
            'transfer_scope' => ['required', Rule::in([StockTransfer::SCOPE_INTERNAL, StockTransfer::SCOPE_EXTERNAL])],
            'from_location_id' => ['required', 'exists:locations,id'],
            'to_location_id' => ['required', 'exists:locations,id', 'different:from_location_id'],
            'transferred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $fromId = (int) $data['from_location_id'];
        $toId = (int) $data['to_location_id'];

        $from = Location::query()->findOrFail($fromId);
        $to = Location::query()->findOrFail($toId);

        if ($data['transfer_scope'] === StockTransfer::SCOPE_EXTERNAL) {
            if (Branch::query()->count() < 2) {
                return back()->withInput()->withErrors([
                    'transfer_scope' => 'Transfert externe impossible : plusieurs branches sont nécessaires.',
                ]);
            }
            if ($this->branchFilterIds() !== null) {
                $hasRemoteWarehouse = Location::query()
                    ->whereIn('kind', [Location::KIND_MAIN, Location::KIND_STORAGE])
                    ->whereNotIn('branch_id', $this->branchFilterIds())
                    ->exists();
                if (! $hasRemoteWarehouse) {
                    return back()->withInput()->withErrors([
                        'transfer_scope' => 'Aucun entrepôt d’une autre branche n’est disponible pour un transfert externe.',
                    ]);
                }
            }
        }

        if ($data['transfer_scope'] === StockTransfer::SCOPE_INTERNAL) {
            if ($this->branchFilterIds() !== null) {
                $allowedLocationIds = $this->locationIdsForUser();
                foreach ([$fromId, $toId] as $lid) {
                    if (! in_array($lid, $allowedLocationIds, true)) {
                        abort(403, 'Emplacement non autorisé.');
                    }
                }
            }
            if ((int) $from->branch_id !== (int) $to->branch_id) {
                return back()->withInput()->withErrors([
                    'to_location_id' => 'Un transfert interne doit rester dans la même branche.',
                ]);
            }
            if (! $from->isMain() && ! $from->isStorage()) {
                return back()->withInput()->withErrors([
                    'from_location_id' => 'La source doit être l’emplacement principal ou un entrepôt secondaire.',
                ]);
            }
            if (! $to->isPointOfSale()) {
                return back()->withInput()->withErrors([
                    'to_location_id' => 'La destination doit être un point de vente.',
                ]);
            }
        } else {
            if ((int) $from->branch_id === (int) $to->branch_id) {
                return back()->withInput()->withErrors([
                    'to_location_id' => 'Un transfert externe doit aller d’une branche à une autre.',
                ]);
            }
            if (! $from->isMain() && ! $from->isStorage()) {
                return back()->withInput()->withErrors([
                    'from_location_id' => 'La source doit être l’emplacement principal ou un entrepôt secondaire.',
                ]);
            }
            if (! $to->isMain() && ! $to->isStorage()) {
                return back()->withInput()->withErrors([
                    'to_location_id' => 'La destination doit être l’emplacement principal ou un entrepôt secondaire.',
                ]);
            }

            if ($this->branchFilterIds() !== null) {
                $allowedBranches = $this->branchFilterIds();
                $allowedLocationIds = $this->locationIdsForUser();
                $fromBranchMine = in_array((int) $from->branch_id, $allowedBranches, true);
                $toBranchMine = in_array((int) $to->branch_id, $allowedBranches, true);
                if (! $fromBranchMine && ! $toBranchMine) {
                    abort(403, 'Au moins une extrémité du transfert doit concerner votre branche.');
                }
                if ($fromBranchMine && ! in_array($fromId, $allowedLocationIds, true)) {
                    abort(403, 'Emplacement source non autorisé.');
                }
                if ($toBranchMine && ! in_array($toId, $allowedLocationIds, true)) {
                    abort(403, 'Emplacement destination non autorisé.');
                }
            }
        }

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

        $occurredOn = Carbon::parse($data['transferred_at'])->startOfDay();

        try {
            $transfer = DB::transaction(function () use ($request, $data, $fromId, $toId, $merged, $occurredOn) {
                $transfer = StockTransfer::create([
                    'from_location_id' => $fromId,
                    'to_location_id' => $toId,
                    'transfer_scope' => $data['transfer_scope'],
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
        abort_unless(auth()->user()?->canViewStockTransfers(), 403);

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

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array{id: int, name: string, branch_id: int, branch_name: string}>
     */
    private function mapLocationOptions($locations): array
    {
        return $locations->map(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'branch_id' => $l->branch_id,
            'branch_name' => $l->branch->name,
        ])->values()->all();
    }
}
