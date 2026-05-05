<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReception;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $reference = 'PO-'.now()->format('Ymd-His').'-'.random_int(100, 999);

        DB::transaction(function () use ($request, $data, $location, $reference) {
            $po = PurchaseOrder::create([
                'reference' => $reference,
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

        $data = $this->validatePurchaseOrderPayload($request);
        $location = Location::query()->findOrFail((int) $data['location_id']);
        abort_unless($location->isMain() || $location->isStorage(), 422, 'Réception des achats uniquement sur l’emplacement principal ou un entrepôt secondaire.');

        DB::transaction(function () use ($purchaseOrder, $data, $location) {
            $purchaseOrder->update([
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
            ->with(['product:id,name', 'receiver:id,name', 'location:id,name'])
            ->simplePaginate(25)
            ->withQueryString();

        $hasRemaining = $purchaseOrder->items->contains(fn ($item) => $item->quantity_received < $item->quantity_ordered);
        $expectedStatus = $hasRemaining ? ($purchaseOrder->items->sum('quantity_received') > 0 ? 'partial' : 'open') : 'received';
        if ($purchaseOrder->status !== $expectedStatus) {
            $purchaseOrder->update(['status' => $expectedStatus]);
            $purchaseOrder->refresh();
        }

        return view('purchase_orders.show', compact('purchaseOrder', 'receptions'));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
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
                $touched = false;
                $items = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->with('product:id,name')
                    ->lockForUpdate()
                    ->get();

                foreach ($items as $item) {
                    $toReceive = (int) ($data['receive'][$item->id] ?? 0);
                    if ($toReceive < 0) {
                        throw new RuntimeException('Quantité de réception invalide.');
                    }

                    $remaining = $item->quantity_ordered - $item->quantity_received;
                    if ($toReceive > $remaining) {
                        throw new RuntimeException("La quantité reçue dépasse le restant pour {$item->product->name}.");
                    }

                    if ($toReceive > 0) {
                        $touched = true;
                        Stock::modifyQuantity((int) $item->product_id, (int) $purchaseOrder->location_id, $toReceive);

                        StockMovement::create([
                            'type' => 'entry',
                            'product_id' => $item->product_id,
                            'quantity' => $toReceive,
                            'from_location_id' => null,
                            'to_location_id' => $purchaseOrder->location_id,
                            'user_id' => $request->user()->id,
                            'notes' => 'Réception PO '.$purchaseOrder->reference,
                        ]);

                        $item->update([
                            'quantity_received' => $item->quantity_received + $toReceive,
                        ]);

                        PurchaseOrderReception::create([
                            'purchase_order_id' => $purchaseOrder->id,
                            'purchase_order_item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'location_id' => $purchaseOrder->location_id,
                            'quantity' => $toReceive,
                            'received_by' => $request->user()->id,
                            'received_at' => now(),
                        ]);
                    }
                }

                if (! $touched) {
                    throw new RuntimeException('Aucune quantité saisie pour la réception.');
                }

                $hasRemaining = $items->contains(fn ($item) => $item->quantity_received < $item->quantity_ordered);
                $purchaseOrder->update([
                    'status' => $hasRemaining ? 'partial' : 'received',
                    'reception_started' => true,
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('danger', $e->getMessage());
        }

        return back()->with('success', 'Réception enregistrée et stock mis à jour.');
    }

    private function validatePurchaseOrderPayload(Request $request): array
    {
        return $request->validate([
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
