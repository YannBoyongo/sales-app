<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReception;
use App\Models\PurchaseOrderReceptionBatch;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = PurchaseOrder::query()
            ->select(['id', 'reference', 'location_id', 'created_by', 'supplier', 'status', 'reception_started', 'created_at'])
            ->with(['location:id,name,branch_id', 'creator:id,name'])
            ->latest();

        if ($this->branchFilterIds() !== null) {
            $query->whereHas('location', function ($q) {
                $this->applyBranchFilter($q);
            });
        }

        $purchaseOrders = $query->simplePaginate(20);

        return view('purchase_orders.index', compact('purchaseOrders'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $locations = Location::query()
            ->with('branch')
            ->whereIn('kind', [Location::KIND_MAIN, Location::KIND_STORAGE])
            ->orderBy('name')
            ->get();
        $products = Product::query()->with('department')->orderBy('name')->get();

        return view('purchase_orders.create', compact('locations', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validatePurchaseOrderPayload($request);

        $location = Location::query()->findOrFail((int) $data['location_id']);
        abort_unless($location->isMain() || $location->isStorage(), 422, 'Réception des achats uniquement sur l’emplacement principal ou un entrepôt secondaire.');

        DB::transaction(function () use ($request, $data, $location) {
            $po = PurchaseOrder::create([
                'reference' => trim($data['reference']),
                'location_id' => $location->id,
                'created_by' => $request->user()->id,
                'supplier' => $data['supplier'] ?? null,
                'status' => 'open',
                'reception_started' => false,
            ]);

            foreach ($data['products'] as $row) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity_ordered' => (int) $row['quantity_ordered'],
                    'quantity_received' => 0,
                ]);
            }
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Bon de commande créé.');
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $purchaseOrder->load(['location.branch', 'items.product']);
        abort_unless($this->canEdit($purchaseOrder), 403, 'Ce bon de commande ne peut plus être modifié car la réception a déjà commencé.');

        $locations = Location::query()
            ->with('branch')
            ->whereIn('kind', [Location::KIND_MAIN, Location::KIND_STORAGE])
            ->orderBy('name')
            ->get();
        $products = Product::query()->with('department')->orderBy('name')->get();

        return view('purchase_orders.edit', compact('purchaseOrder', 'locations', 'products'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $purchaseOrder->load('items');
        if (! $this->canEdit($purchaseOrder)) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('danger', 'Modification impossible: la réception du PO a déjà commencé.');
        }

        $data = $this->validatePurchaseOrderPayload($request, $purchaseOrder);
        $location = Location::query()->findOrFail((int) $data['location_id']);
        abort_unless($location->isMain() || $location->isStorage(), 422, 'Réception des achats uniquement sur l’emplacement principal ou un entrepôt secondaire.');

        DB::transaction(function () use ($purchaseOrder, $data, $location) {
            $purchaseOrder->update([
                'reference' => trim($data['reference']),
                'location_id' => $location->id,
                'supplier' => $data['supplier'] ?? null,
                'status' => 'open',
                'reception_started' => false,
            ]);

            PurchaseOrderItem::query()->where('purchase_order_id', $purchaseOrder->id)->delete();
            foreach ($data['products'] as $row) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity_ordered' => (int) $row['quantity_ordered'],
                    'quantity_received' => 0,
                ]);
            }
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Bon de commande mis à jour.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'location.branch',
            'creator',
            'items.product.department',
        ]);
        $this->ensureUserCanAccessLocation($purchaseOrder->location);

        $receptions = $purchaseOrder->receptions()
            ->latest('received_at')
            ->with(['product:id,name', 'receiver:id,name', 'location:id,name', 'batch.approver:id,name'])
            ->simplePaginate(25)
            ->withQueryString();

        $pendingBatches = $purchaseOrder->receptionBatches()
            ->where('status', PurchaseOrderReceptionBatch::STATUS_PENDING)
            ->with(['submitter:id,name', 'receptions.product:id,name'])
            ->latest('submitted_at')
            ->get();

        $pendingReceiveByItem = PurchaseOrderReception::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('batch', fn ($q) => $q->where('status', PurchaseOrderReceptionBatch::STATUS_PENDING))
            ->selectRaw('purchase_order_item_id, SUM(quantity) as qty')
            ->groupBy('purchase_order_item_id')
            ->pluck('qty', 'purchase_order_item_id');

        $hasRemaining = $purchaseOrder->items->contains(fn ($item) => $item->quantity_received < $item->quantity_ordered);
        $expectedStatus = $hasRemaining ? ($purchaseOrder->items->sum('quantity_received') > 0 ? 'partial' : 'open') : 'received';
        if ($purchaseOrder->status !== $expectedStatus) {
            $purchaseOrder->update(['status' => $expectedStatus]);
            $purchaseOrder->refresh();
        }

        return view('purchase_orders.show', compact('purchaseOrder', 'receptions', 'pendingBatches', 'pendingReceiveByItem'));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $purchaseOrder->load(['location', 'items.product']);
        $this->ensureUserCanAccessLocation($purchaseOrder->location);

        $hasRemainingAtStart = $purchaseOrder->items->contains(
            fn ($item) => $item->quantity_received < $item->quantity_ordered
        );
        if (! $hasRemainingAtStart) {
            $purchaseOrder->update(['status' => 'received']);

            return back()->with('warning', 'Ce bon de commande est déjà totalement réceptionné.');
        }

        $data = $request->validate([
            'receive' => ['required', 'array'],
            'receive.*' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($request, $purchaseOrder, $data) {
                $items = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->with('product:id,name')
                    ->lockForUpdate()
                    ->get();

                $pendingByItem = PurchaseOrderReception::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->whereHas('batch', fn ($q) => $q->where('status', PurchaseOrderReceptionBatch::STATUS_PENDING))
                    ->selectRaw('purchase_order_item_id, SUM(quantity) as qty')
                    ->groupBy('purchase_order_item_id')
                    ->pluck('qty', 'purchase_order_item_id');

                $lines = [];
                foreach ($items as $item) {
                    $toReceive = (int) ($data['receive'][$item->id] ?? 0);
                    if ($toReceive < 0) {
                        throw new RuntimeException('Quantité de réception invalide.');
                    }

                    $pending = (int) ($pendingByItem[$item->id] ?? 0);
                    $remaining = $item->quantity_ordered - $item->quantity_received - $pending;
                    if ($toReceive > $remaining) {
                        throw new RuntimeException("La quantité reçue dépasse le restant pour {$item->product->name}.");
                    }

                    if ($toReceive > 0) {
                        $lines[] = ['item' => $item, 'quantity' => $toReceive];
                    }
                }

                if ($lines === []) {
                    throw new RuntimeException('Aucune quantité saisie pour la réception.');
                }

                $submittedAt = now();
                $batch = PurchaseOrderReceptionBatch::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'submitted_by' => $request->user()->id,
                    'submitted_at' => $submittedAt,
                    'status' => PurchaseOrderReceptionBatch::STATUS_PENDING,
                ]);

                foreach ($lines as $line) {
                    PurchaseOrderReception::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'reception_batch_id' => $batch->id,
                        'purchase_order_item_id' => $line['item']->id,
                        'product_id' => $line['item']->product_id,
                        'location_id' => $purchaseOrder->location_id,
                        'quantity' => $line['quantity'],
                        'received_by' => $request->user()->id,
                        'received_at' => $submittedAt,
                    ]);
                }

                $purchaseOrder->update(['reception_started' => true]);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('danger', $e->getMessage());
        }

        return back()->with('success', 'Réception soumise. En attente d’approbation par un administrateur.');
    }

    public function approveReceptionBatch(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderReceptionBatch $batch): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        abort_unless((int) $batch->purchase_order_id === (int) $purchaseOrder->id, 404);
        abort_unless($batch->isPending(), 422, 'Cette réception a déjà été traitée.');

        $purchaseOrder->load(['location', 'items.product']);
        $this->ensureUserCanAccessLocation($purchaseOrder->location);

        try {
            DB::transaction(function () use ($request, $purchaseOrder, $batch) {
                $batch = PurchaseOrderReceptionBatch::query()
                    ->whereKey($batch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $batch->isPending()) {
                    throw new RuntimeException('Cette réception a déjà été traitée.');
                }

                $receptions = PurchaseOrderReception::query()
                    ->where('reception_batch_id', $batch->id)
                    ->with(['item.product:id,name'])
                    ->lockForUpdate()
                    ->get();

                $items = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($receptions as $reception) {
                    $item = $items->get($reception->purchase_order_item_id);
                    if (! $item) {
                        throw new RuntimeException('Ligne de bon de commande introuvable.');
                    }

                    $remaining = $item->quantity_ordered - $item->quantity_received;
                    if ($reception->quantity > $remaining) {
                        throw new RuntimeException("La quantité approuvée dépasse le restant pour {$item->product->name}.");
                    }

                    Stock::modifyQuantity((int) $reception->product_id, (int) $purchaseOrder->location_id, (int) $reception->quantity);

                    StockMovement::create([
                        'type' => 'entry',
                        'product_id' => $reception->product_id,
                        'quantity' => $reception->quantity,
                        'from_location_id' => null,
                        'to_location_id' => $purchaseOrder->location_id,
                        'user_id' => $request->user()->id,
                        'notes' => 'Réception PO '.$purchaseOrder->reference,
                    ]);

                    $item->update([
                        'quantity_received' => $item->quantity_received + $reception->quantity,
                    ]);
                }

                $batch->update([
                    'status' => PurchaseOrderReceptionBatch::STATUS_APPROVED,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                $this->syncPurchaseOrderStatus($purchaseOrder);
            });
        } catch (RuntimeException $e) {
            return back()->with('danger', $e->getMessage());
        }

        return back()->with('success', 'Réception approuvée et stock mis à jour.');
    }

    public function rejectReceptionBatch(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderReceptionBatch $batch): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        abort_unless((int) $batch->purchase_order_id === (int) $purchaseOrder->id, 404);
        abort_unless($batch->isPending(), 422, 'Cette réception a déjà été traitée.');

        $this->ensureUserCanAccessLocation($purchaseOrder->location);

        DB::transaction(function () use ($request, $batch) {
            $batch = PurchaseOrderReceptionBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $batch->isPending()) {
                return;
            }

            $batch->update([
                'status' => PurchaseOrderReceptionBatch::STATUS_REJECTED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', 'Réception refusée.');
    }

    private function syncPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load('items');
        $hasRemaining = $purchaseOrder->items->contains(fn ($item) => $item->quantity_received < $item->quantity_ordered);

        $purchaseOrder->update([
            'status' => $hasRemaining
                ? ($purchaseOrder->items->sum('quantity_received') > 0 ? 'partial' : 'open')
                : 'received',
            'reception_started' => true,
        ]);
    }

    private function validatePurchaseOrderPayload(Request $request, ?PurchaseOrder $purchaseOrder = null): array
    {
        $referenceRule = Rule::unique('purchase_orders', 'reference');
        if ($purchaseOrder) {
            $referenceRule->ignore($purchaseOrder->id);
        }

        return $request->validate([
            'reference' => ['required', 'string', 'max:100', $referenceRule],
            'location_id' => ['required', 'exists:locations,id'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.quantity_ordered' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function canEdit(PurchaseOrder $purchaseOrder): bool
    {
        return ! (bool) $purchaseOrder->reception_started;
    }
}
