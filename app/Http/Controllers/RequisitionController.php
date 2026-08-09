<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Stock;
use App\Models\StockBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequisitionController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $requisitions = Requisition::query()
            ->with('creator:id,name')
            ->latest('date')
            ->latest('id')
            ->paginate(20);

        return view('requisitions.index', compact('requisitions'));
    }

    public function create(): View
    {
        return view('requisitions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $requisition = Requisition::query()->create([
            'reference' => Requisition::generateReference(),
            'date' => $data['date'],
            'status' => Requisition::STATUS_PENDING,
            'expenses' => 0,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('requisitions.edit', $requisition)
            ->with('success', 'Réquisition '.$requisition->reference.' créée (en attente).');
    }

    public function show(Requisition $requisition): View
    {
        $requisition->load([
            'creator:id,name',
            'items.product:id,name,sku',
            'purchaseOrders:id,reference,requisition_id,status',
        ]);

        $canEditItems = $requisition->isEditable();
        $canConvertToPo = $requisition->canConvertToPurchaseOrder();
        $receptionLocations = $canConvertToPo
            ? Location::query()
                ->with('branch:id,name')
                ->whereIn('kind', [Location::KIND_MAIN, Location::KIND_STORAGE])
                ->orderBy('name')
                ->get(['id', 'name', 'branch_id', 'kind'])
            : collect();

        $requisitionItems = $this->mapRequisitionItems($requisition);

        return view('requisitions.show', compact(
            'requisition',
            'requisitionItems',
            'canEditItems',
            'canConvertToPo',
            'receptionLocations',
        ));
    }

    public function edit(Request $request, Requisition $requisition): View
    {
        abort_unless($requisition->isEditable(), 403, 'Seules les réquisitions en attente peuvent être modifiées.');

        $requisition->load([
            'creator:id,name',
            'items.product:id,name,sku',
        ]);

        $filters = $request->validate([
            'stock_scope' => ['nullable', 'in:all,out_of_stock'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $stockScope = $filters['stock_scope'] ?? 'out_of_stock';
        $departmentId = isset($filters['department_id']) ? (int) $filters['department_id'] : null;

        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        $catalogQuery = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->when($departmentId, fn ($q) => $q->where('products.department_id', $departmentId))
            ->selectRaw('stocks.product_id, products.name as product_name, SUM(stocks.quantity) as total_quantity')
            ->groupBy('stocks.product_id', 'products.name')
            ->orderBy('products.name');
        $this->applyStockBranchFilter($catalogQuery);

        if ($stockScope === 'out_of_stock') {
            $catalogQuery->havingRaw('SUM(stocks.quantity) <= 0');
        }

        $totals = $catalogQuery->get();
        $products = Product::query()
            ->with('department:id,name')
            ->whereIn('id', $totals->pluck('product_id'))
            ->get(['id', 'name', 'sku', 'department_id'])
            ->keyBy('id');

        $catalogItems = $totals
            ->map(function ($row) use ($products) {
                return (object) [
                    'product_id' => (int) $row->product_id,
                    'total_quantity' => (int) $row->total_quantity,
                    'product' => $products->get($row->product_id),
                ];
            })
            ->values();

        $requisitionItems = $this->mapRequisitionItems($requisition);

        return view('requisitions.edit', [
            'requisition' => $requisition,
            'catalogItems' => $catalogItems,
            'requisitionItems' => $requisitionItems,
            'departments' => $departments,
            'canEditItems' => true,
            'filters' => [
                'stock_scope' => $stockScope,
                'department_id' => $departmentId,
            ],
        ]);
    }

    public function syncItems(Request $request, Requisition $requisition): RedirectResponse
    {
        if (! $requisition->isEditable()) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Seules les réquisitions en attente peuvent être modifiées.');
        }

        $confirm = $request->boolean('confirm');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'expenses' => ['nullable', 'numeric', 'min:0'],
            'items' => $confirm ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        $existingByKey = $requisition->items()
            ->get(['product_id', 'batch_number', 'unit_price', 'tax'])
            ->keyBy(fn (RequisitionItem $item) => $item->product_id.'|'.(string) ($item->batch_number ?? ''));

        $items = collect($data['items'] ?? [])
            ->map(function (array $item) {
                $batch = filled($item['batch_number'] ?? null) ? trim((string) $item['batch_number']) : '';

                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'batch_number' => $batch,
                    'unit_price' => array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                        ? round((float) $item['unit_price'], 2)
                        : null,
                    'tax' => array_key_exists('tax', $item) && $item['tax'] !== null && $item['tax'] !== ''
                        ? round((float) $item['tax'], 2)
                        : null,
                ];
            })
            ->unique(fn (array $item) => $item['product_id'].'|'.$item['batch_number'])
            ->map(function (array $item) use ($existingByKey) {
                $existing = $existingByKey->get($item['product_id'].'|'.$item['batch_number']);

                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'batch_number' => $item['batch_number'],
                    'unit_price' => $item['unit_price'] ?? round((float) ($existing?->unit_price ?? 0), 2),
                    'tax' => $item['tax'] ?? round((float) ($existing?->tax ?? 0), 2),
                ];
            })
            ->values();

        if ($confirm && $items->isEmpty()) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Ajoutez au moins un article avant de confirmer.');
        }

        $expenses = array_key_exists('expenses', $data) && $data['expenses'] !== null
            ? round((float) $data['expenses'], 2)
            : round((float) $requisition->expenses, 2);

        $allocated = Requisition::allocateExpensesToItems($items, $expenses);

        DB::transaction(function () use ($requisition, $allocated, $data, $expenses, $confirm) {
            $payload = [
                'date' => $data['date'],
                'expenses' => $expenses,
            ];

            if ($confirm) {
                $payload['status'] = Requisition::STATUS_CONFIRMED;
            }

            $requisition->update($payload);

            RequisitionItem::query()->where('requisition_id', $requisition->id)->delete();

            foreach ($allocated as $item) {
                RequisitionItem::query()->create([
                    'requisition_id' => $requisition->id,
                    'product_id' => (int) $item['product_id'],
                    'location_id' => null,
                    'quantity' => (int) $item['quantity'],
                    'batch_number' => $item['batch_number'] ?? '',
                    'unit_price' => $item['unit_price'],
                    'tax' => $item['tax'],
                    'other' => $item['other'],
                    'cost' => $item['cost'],
                ]);
            }
        });

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('success', $confirm
                ? 'Réquisition confirmée.'
                : 'Réquisition enregistrée (toujours en attente).');
    }

    public function convertToPurchaseOrder(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($request->user()->hasApplicationAdminAccess(), 403);

        if (! $requisition->canConvertToPurchaseOrder()) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Cette réquisition ne peut pas être convertie en bon de commande.');
        }

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:purchase_orders,reference'],
        ]);

        $location = Location::query()->findOrFail((int) $data['location_id']);
        abort_unless($location->isMain() || $location->isStorage(), 422, 'Réception des achats uniquement sur l’emplacement principal ou un entrepôt secondaire.');

        $requisition->load('items.product:id,name');

        if ($requisition->items->isEmpty()) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Ajoutez des articles avant de créer le bon de commande.');
        }

        try {
            $purchaseOrder = DB::transaction(function () use ($request, $requisition, $data, $location) {
                $requisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
                $requisition->load('items');

                if ($requisition->status !== Requisition::STATUS_CONFIRMED
                    || $requisition->items->isEmpty()
                    || PurchaseOrder::query()->where('requisition_id', $requisition->id)->exists()) {
                    throw new \RuntimeException('Cette réquisition ne peut pas être convertie en bon de commande.');
                }

                $purchaseOrder = PurchaseOrder::query()->create([
                    'reference' => filled($data['reference'] ?? null)
                        ? trim((string) $data['reference'])
                        : PurchaseOrder::generateReferenceFromRequisition($requisition),
                    'requisition_id' => $requisition->id,
                    'location_id' => $location->id,
                    'created_by' => $request->user()->id,
                    'supplier' => $data['supplier'] ?? null,
                    'status' => 'open',
                    'reception_started' => false,
                ]);

                foreach ($requisition->items as $item) {
                    $qty = max(1, (int) $item->quantity);
                    $lineCost = round((float) $item->cost, 2);
                    $unitCost = round($lineCost / $qty, 2);
                    $batchNumber = StockBatch::normalizeBatchNumber(
                        $item->batch_number,
                        'REQ-'.$requisition->id.'-'.$item->id
                    );

                    PurchaseOrderItem::query()->create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => (int) $item->product_id,
                        'batch_number' => $batchNumber,
                        'quantity_ordered' => $qty,
                        'quantity_received' => 0,
                        'unit_price' => round((float) $item->unit_price, 2),
                        'tax' => round((float) $item->tax, 2),
                        'other' => round((float) $item->other, 2),
                        'unit_cost' => $unitCost,
                        'line_cost' => $lineCost,
                        'requisition_item_id' => $item->id,
                    ]);
                }

                $requisition->update(['status' => Requisition::STATUS_ORDERED]);

                return $purchaseOrder;
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', $e->getMessage());
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Bon de commande '.$purchaseOrder->reference.' créé depuis la réquisition.');
    }

    public function update(Request $request, Requisition $requisition): RedirectResponse
    {
        if (! $requisition->isEditable()) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Seules les réquisitions en attente peuvent être modifiées.');
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in([
                Requisition::STATUS_PENDING,
                Requisition::STATUS_CONFIRMED,
                Requisition::STATUS_ORDERED,
                Requisition::STATUS_REJECTED,
            ])],
        ]);

        $requisition->update([
            'date' => $data['date'],
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('success', 'Réquisition mise à jour.');
    }

    public function destroy(Requisition $requisition): RedirectResponse
    {
        if (! $requisition->isEditable()) {
            return redirect()
                ->route('requisitions.index')
                ->with('danger', 'Seules les réquisitions en attente peuvent être supprimées.');
        }

        RequisitionItem::query()->where('requisition_id', $requisition->id)->delete();
        $requisition->delete();

        return redirect()
            ->route('requisitions.index')
            ->with('success', 'Réquisition supprimée.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function mapRequisitionItems(Requisition $requisition)
    {
        $mapped = $requisition->items->map(fn (RequisitionItem $item) => [
            'product_id' => $item->product_id,
            'quantity' => (int) $item->quantity,
            'batch_number' => $item->batch_number ?? '',
            'unit_price' => round((float) $item->unit_price, 2),
            'tax' => round((float) $item->tax, 2),
            'other' => round((float) $item->other, 2),
            'cost' => round((float) $item->cost, 2),
            'product_name' => $item->product?->name ?? '—',
            'product_sku' => $item->product?->sku,
        ])->values();

        return Requisition::allocateExpensesToItems($mapped, (float) $requisition->expenses)
            ->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'batch_number' => $item['batch_number'] ?? null,
                'unit_price' => $item['unit_price'],
                'tax' => $item['tax'],
                'other' => $item['other'],
                'cost' => $item['cost'],
                'unit_cost' => $item['unit_cost'],
                'cost_total' => $item['cost_total'],
                'share_percent' => $item['share_percent'],
                'merchandise' => $item['merchandise'],
                'product_name' => $item['product_name'] ?? '—',
                'product_sku' => $item['product_sku'] ?? null,
            ])
            ->values();
    }
}
