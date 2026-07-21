<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Stock;
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
            'status' => Requisition::STATUS_OPEN,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('requisitions.edit', $requisition)
            ->with('success', 'Réquisition '.$requisition->reference.' créée.');
    }

    public function show(Requisition $requisition): View
    {
        $requisition->load('creator:id,name');

        return view('requisitions.show', compact('requisition'));
    }

    public function edit(Request $request, Requisition $requisition): View
    {
        abort_unless($requisition->status === Requisition::STATUS_OPEN, 403, 'Seules les réquisitions ouvertes peuvent être modifiées.');

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

        $requisitionItems = $requisition->items
            ->map(fn (RequisitionItem $item) => [
                'product_id' => $item->product_id,
                'quantity' => (int) $item->quantity,
                'product_name' => $item->product?->name ?? '—',
                'product_sku' => $item->product?->sku,
            ])
            ->values();

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
        if ($requisition->status !== Requisition::STATUS_OPEN) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Seules les réquisitions ouvertes peuvent être modifiées.');
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $items = collect($data['items'] ?? [])
            ->unique(fn ($item) => $item['product_id'])
            ->values();

        DB::transaction(function () use ($requisition, $items, $data) {
            $requisition->update([
                'date' => $data['date'],
            ]);

            RequisitionItem::query()->where('requisition_id', $requisition->id)->delete();

            foreach ($items as $item) {
                RequisitionItem::query()->create([
                    'requisition_id' => $requisition->id,
                    'product_id' => (int) $item['product_id'],
                    'location_id' => null,
                    'quantity' => (int) $item['quantity'],
                ]);
            }
        });

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('success', 'Réquisition enregistrée.');
    }

    public function update(Request $request, Requisition $requisition): RedirectResponse
    {
        if ($requisition->status !== Requisition::STATUS_OPEN) {
            return redirect()
                ->route('requisitions.show', $requisition)
                ->with('danger', 'Seules les réquisitions ouvertes peuvent être modifiées.');
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in([
                Requisition::STATUS_OPEN,
                Requisition::STATUS_APPROVED,
                Requisition::STATUS_REJECTED,
                Requisition::STATUS_FULFILLED,
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
        if ($requisition->status !== Requisition::STATUS_OPEN) {
            return redirect()
                ->route('requisitions.index')
                ->with('danger', 'Seules les réquisitions ouvertes peuvent être supprimées.');
        }

        RequisitionItem::query()->where('requisition_id', $requisition->id)->delete();
        $requisition->delete();

        return redirect()
            ->route('requisitions.index')
            ->with('success', 'Réquisition supprimée.');
    }
}
