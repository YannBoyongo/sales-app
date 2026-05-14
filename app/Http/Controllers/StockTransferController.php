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

        $warehouseKinds = [Location::KIND_MAIN, Location::KIND_STORAGE];

        $user = auth()->user();
        if ($user->isStockManager()) {
            $distinctBranches = $user->managedLocations()->get()->pluck('branch_id')->unique()->filter()->count();
            $picksBranchForTransfer = $distinctBranches > 1;
        } else {
            $picksBranchForTransfer = $this->branchFilterIds() === null;
        }

        if ($user->isStockManager()) {
            $branchesForTransfer = $this->stockBranchesForMatrix()
                ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])
                ->values()
                ->all();
        } else {
            $branchesForTransfer = Branch::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])
                ->values()
                ->all();
        }

        // Magasinier : sources (entrepôts) limitées aux emplacements assignés ; destinations internes = tous les points de vente des branches où il a au moins un emplacement.
        // Admin sans filtre branche : listes globales, filtrées côté client par la branche choisie.
        $applyInternalLocationScope = ! $picksBranchForTransfer || $user->isStockManager();

        $userBranchName = null;
        if (! $picksBranchForTransfer) {
            $userBranchName = auth()->user()->branch?->name
                ?? $this->branchesForUser()->first()?->name;
        }

        $internalFromQuery = Location::query()
            ->with('branch:id,name')
            ->whereIn('kind', $warehouseKinds)
            ->orderBy('name');
        if ($applyInternalLocationScope) {
            $this->applyBranchFilter($internalFromQuery, 'branch_id');
        }
        $internalFromLocations = $this->mapLocationOptions($internalFromQuery->get());

        $internalToQuery = Location::query()
            ->with('branch:id,name')
            ->where('kind', Location::KIND_POINT_OF_SALE)
            ->orderBy('name');
        if ($applyInternalLocationScope) {
            if ($user->isStockManager()) {
                $branchIds = $this->branchIdsForStockManagerInternalDestinations();
                if ($branchIds === []) {
                    $internalToQuery->whereRaw('1 = 0');
                } else {
                    $table = $internalToQuery->getModel()->getTable();
                    $internalToQuery->whereIn($table.'.branch_id', $branchIds);
                }
            } else {
                $this->applyBranchFilter($internalToQuery, 'branch_id');
            }
        }
        $internalToLocations = $this->mapLocationOptions($internalToQuery->get());

        $externalPickMode = 'split';
        $externalLocations = [];
        $externalFromLocations = [];
        $externalToLocations = [];

        if ($user?->isStockManager()) {
            $externalPickMode = 'single_list';
            $externalQuery = Location::query()
                ->with('branch:id,name')
                ->whereIn('kind', $warehouseKinds)
                ->orderBy('name');
            $this->applyBranchFilter($externalQuery, 'branch_id');
            $externalLocations = $this->mapLocationOptions($externalQuery->get());
            $externalFromLocations = [];
            $externalToLocations = [];
            $managedDistinctBranches = $user->managedLocations()->get()->pluck('branch_id')->unique()->filter()->count();
            $canExternal = $managedDistinctBranches >= 2 && Branch::query()->count() >= 2;
        } elseif ($this->branchFilterIds() === null) {
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

        if (! $user?->isStockManager()) {
            $canExternal = $this->branchFilterIds() === null
                ? Branch::query()->count() >= 2
                : (count($externalFromLocations) > 0 && count($externalToLocations) > 0);
        }

        return view('stock_transfers.create', compact(
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
            if ($request->user()?->isStockManager()) {
                $managedLocIds = $this->managedLocationIdsForUser() ?? [];
                $managedBranchIds = $managedLocIds === []
                    ? []
                    : Location::query()
                        ->whereIn('id', $managedLocIds)
                        ->distinct()
                        ->pluck('branch_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                if ($managedBranchIds === []) {
                    return back()->withInput()->withErrors([
                        'transfer_scope' => 'Aucun emplacement n’est associé à votre compte magasinier pour un transfert externe.',
                    ]);
                }
                $hasRemoteWarehouse = Location::query()
                    ->whereIn('kind', [Location::KIND_MAIN, Location::KIND_STORAGE])
                    ->whereNotIn('branch_id', $managedBranchIds)
                    ->exists();
                if (! $hasRemoteWarehouse) {
                    return back()->withInput()->withErrors([
                        'transfer_scope' => 'Aucun entrepôt d’une autre branche n’est disponible pour un transfert externe.',
                    ]);
                }
            } elseif ($this->branchFilterIds() !== null) {
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
            if ($request->user()?->isStockManager()) {
                if (! in_array($fromId, $this->locationIdsForUser(), true)) {
                    abort(403, 'Emplacement source non autorisé.');
                }
            } elseif ($this->branchFilterIds() !== null) {
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

            if ($request->user()?->isStockManager()) {
                $allowedLocationIds = $this->locationIdsForUser();
                $managedLocIds = $this->managedLocationIdsForUser() ?? [];
                $managedBranchIds = $managedLocIds === []
                    ? []
                    : Location::query()
                        ->whereIn('id', $managedLocIds)
                        ->distinct()
                        ->pluck('branch_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                $fromBranchManaged = in_array((int) $from->branch_id, $managedBranchIds, true);
                $toBranchManaged = in_array((int) $to->branch_id, $managedBranchIds, true);
                if (! $fromBranchManaged && ! $toBranchManaged) {
                    abort(403, 'Au moins une extrémité du transfert doit concerner un de vos emplacements.');
                }
                if ($fromBranchManaged && ! in_array($fromId, $allowedLocationIds, true)) {
                    abort(403, 'Emplacement source non autorisé.');
                }
                if ($toBranchManaged && ! in_array($toId, $allowedLocationIds, true)) {
                    abort(403, 'Emplacement destination non autorisé.');
                }
            } elseif ($this->branchFilterIds() !== null) {
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

        $occurredOn = Carbon::parse($data['transferred_at'])->startOfDay();

        $transfer = StockTransfer::create([
            'from_location_id' => $fromId,
            'to_location_id' => $toId,
            'transfer_scope' => $data['transfer_scope'],
            'transferred_at' => $occurredOn,
            'user_id' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'status' => StockTransfer::STATUS_PENDING,
        ]);

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', 'Transfert créé (en attente). Ajoutez les articles, puis confirmez pour mettre à jour les stocks.');
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

        $canManageTransfer = auth()->user()?->canManageStockTransfers() ?? false;

        $lineQtyByProduct = $stockTransfer->items->pluck('quantity', 'product_id');

        $transferProducts = [];
        if ($canManageTransfer && $stockTransfer->isPending()) {
            $productQuery = Product::query()->orderBy('name');
            $this->applyProductBranchScope($productQuery);
            $products = $productQuery->get(['id', 'name', 'sku']);

            $fromLocationId = (int) $stockTransfer->from_location_id;
            $stockQtyByProduct = $products->isEmpty()
                ? collect()
                : Stock::query()
                    ->where('location_id', $fromLocationId)
                    ->whereIn('product_id', $products->modelKeys())
                    ->pluck('quantity', 'product_id');

            $transferProducts = $products->map(function (Product $p) use ($stockQtyByProduct, $lineQtyByProduct) {
                $atSource = (int) ($stockQtyByProduct[$p->getKey()] ?? 0);
                $onLine = (int) ($lineQtyByProduct[$p->getKey()] ?? 0);

                return [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'label' => $p->name.($p->sku ? ' — '.$p->sku : ''),
                    'stock_qty' => max(0, $atSource - $onLine),
                ];
            })->values()->all();
        }

        return view('stock_transfers.show', compact('stockTransfer', 'canManageTransfer', 'transferProducts'));
    }

    public function storeItem(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        abort_unless($request->user()?->canManageStockTransfers(), 403);

        $this->ensureUserCanAccessStockTransfer($stockTransfer);

        if (! $stockTransfer->isPending()) {
            return back()->withErrors([
                'stock' => 'Ce transfert est déjà confirmé ; les lignes ne peuvent plus être modifiées.',
            ]);
        }

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $productId = (int) $data['product_id'];
        $qty = (int) $data['quantity'];

        $fromId = (int) $stockTransfer->from_location_id;

        if ($this->managedLocationIdsForUser() !== null || $this->branchFilterIds() !== null) {
            $productQuery = Product::query()->whereKey($productId);
            $this->applyProductBranchScope($productQuery);
            abort_unless($productQuery->exists(), 403, 'Produit non autorisé.');
        }

        try {
            DB::transaction(function () use ($stockTransfer, $productId, $qty, $fromId) {
                StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->first();

                $item = StockTransferItem::query()
                    ->where('stock_transfer_id', $stockTransfer->id)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                $newTotal = ($item?->quantity ?? 0) + $qty;

                $stock = Stock::query()
                    ->where('product_id', $productId)
                    ->where('location_id', $fromId)
                    ->lockForUpdate()
                    ->first();
                $available = (int) ($stock?->quantity ?? 0);

                if ($newTotal > $available) {
                    throw new RuntimeException('Stock insuffisant à l’emplacement source pour cette quantité.');
                }

                if ($item) {
                    $item->update(['quantity' => $item->quantity + $qty]);
                } else {
                    StockTransferItem::create([
                        'stock_transfer_id' => $stockTransfer->id,
                        'product_id' => $productId,
                        'quantity' => $qty,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Ligne ajoutée au transfert.');
    }

    public function destroyItem(StockTransfer $stockTransfer, StockTransferItem $stock_transfer_item): RedirectResponse
    {
        abort_unless(auth()->user()?->canManageStockTransfers(), 403);

        $this->ensureUserCanAccessStockTransfer($stockTransfer);

        abort_unless((int) $stock_transfer_item->stock_transfer_id === (int) $stockTransfer->id, 404);

        if (! $stockTransfer->isPending()) {
            return back()->withErrors([
                'stock' => 'Ce transfert est déjà confirmé ; les lignes ne peuvent plus être modifiées.',
            ]);
        }

        DB::transaction(function () use ($stockTransfer, $stock_transfer_item) {
            StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->first();
            $stock_transfer_item->delete();
        });

        return back()->with('success', 'Ligne retirée du transfert.');
    }

    public function confirm(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        abort_unless($request->user()?->canManageStockTransfers(), 403);

        $this->ensureUserCanAccessStockTransfer($stockTransfer);

        if (! $stockTransfer->isPending()) {
            return back()->withErrors(['stock' => 'Ce transfert est déjà confirmé.']);
        }

        $stockTransfer->load('items');

        if ($stockTransfer->items->isEmpty()) {
            return back()->withErrors(['stock' => 'Ajoutez au moins une ligne avant de confirmer.']);
        }

        $fromId = (int) $stockTransfer->from_location_id;
        $toId = (int) $stockTransfer->to_location_id;
        $occurredOn = $stockTransfer->transferred_at->copy()->startOfDay();

        foreach ($stockTransfer->items as $item) {
            if ($this->managedLocationIdsForUser() !== null || $this->branchFilterIds() !== null) {
                $productQuery = Product::query()->whereKey($item->product_id);
                $this->applyProductBranchScope($productQuery);
                abort_unless($productQuery->exists(), 403, 'Produit non autorisé.');
            }
        }

        try {
            DB::transaction(function () use ($request, $stockTransfer, $fromId, $toId, $occurredOn) {
                $transfer = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->first();
                if (! $transfer || ! $transfer->isPending()) {
                    throw new RuntimeException('Ce transfert ne peut pas être confirmé.');
                }

                $items = StockTransferItem::query()
                    ->where('stock_transfer_id', $transfer->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($items->isEmpty()) {
                    throw new RuntimeException('Ajoutez au moins une ligne avant de confirmer.');
                }

                foreach ($items as $item) {
                    $pid = (int) $item->product_id;
                    $q = (int) $item->quantity;

                    Stock::modifyQuantity($pid, $fromId, -$q);
                    Stock::modifyQuantity($pid, $toId, $q);

                    StockMovement::create([
                        'type' => 'transfer',
                        'product_id' => $pid,
                        'quantity' => $q,
                        'from_location_id' => $fromId,
                        'to_location_id' => $toId,
                        'user_id' => $request->user()->id,
                        'stock_transfer_id' => $transfer->id,
                        'notes' => null,
                        'occurred_on' => $occurredOn,
                    ]);
                }

                $transfer->update(['status' => StockTransfer::STATUS_CONFIRMED]);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Transfert confirmé : les stocks ont été mis à jour.');
    }

    private function applyStockTransferBranchFilter(Builder $query): void
    {
        $managed = $this->managedLocationIdsForUser();
        if ($managed !== null) {
            if ($managed === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where(function (Builder $q) use ($managed) {
                $q->whereIn('from_location_id', $managed)
                    ->orWhereIn('to_location_id', $managed);
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
            $q->whereHas('fromLocation', fn (Builder $l) => $l->whereIn('branch_id', $ids))
                ->orWhereHas('toLocation', fn (Builder $l) => $l->whereIn('branch_id', $ids));
        });
    }

    private function ensureUserCanAccessStockTransfer(StockTransfer $stockTransfer): void
    {
        $managed = $this->managedLocationIdsForUser();
        if ($managed !== null) {
            if ($managed === []) {
                abort(403, 'Accès non autorisé.');
            }
            $fromOk = in_array((int) $stockTransfer->from_location_id, $managed, true);
            $toOk = in_array((int) $stockTransfer->to_location_id, $managed, true);
            abort_unless($fromOk || $toOk, 403, 'Accès non autorisé pour ce transfert.');

            return;
        }

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
     * Branches où le magasinier a au moins un emplacement : en transfert interne, tout point de vente de ces branches peut être choisi en destination (les sources restent limitées aux emplacements assignés).
     *
     * @return list<int>
     */
    private function branchIdsForStockManagerInternalDestinations(): array
    {
        $user = auth()->user();
        if (! $user?->isStockManager()) {
            return [];
        }

        $locIds = $this->managedLocationIdsForUser() ?? [];
        if ($locIds === []) {
            return [];
        }

        return Location::query()
            ->whereIn('id', $locIds)
            ->distinct()
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array{id: int, name: string, branch_id: int, branch_name: string}>
     */
    private function mapLocationOptions($locations): array
    {
        return $locations->map(fn (Location $l) => [
            'id' => (int) $l->id,
            'name' => $l->name,
            // Normalize for @js(): MySQL can expose FKs as strings; Alpine uses strict === against Number(branchId).
            'branch_id' => (int) $l->branch_id,
            'branch_name' => $l->branch->name,
        ])->values()->all();
    }
}
