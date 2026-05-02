<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesSession;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use RuntimeException;

class SaleItemController extends Controller
{
    use RespectsUserBranch;

    public function show(SalesSession $salesSession, SaleItem $saleItem): View
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless((int) $saleItem->sales_session_id === (int) $salesSession->id, 404);

        $saleItem->load([
            'product.department',
            'location',
            'user',
            'client',
            'salesSession.branch',
        ]);

        return view('sale_items.show', compact('salesSession', 'saleItem'));
    }

    public function create(SalesSession $salesSession): View
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        if (! $salesSession->isOpen()) {
            abort(403, 'Impossible d’enregistrer une vente sur une session fermée.');
        }

        $locations = Location::query()
            ->where('branch_id', $salesSession->branch_id)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);

        $saleCatalog = Department::query()
            ->whereHas('products', function ($q) {
                $this->applyProductBranchScope($q);
            })
            ->with(['products' => function ($q) {
                $this->applyProductBranchScope($q);
                $q->select(['id', 'department_id', 'name', 'unit_price'])
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'products' => $d->products->map(fn (Product $p) => [
                    'id' => $p->id,
                    'label' => $p->name.' — '.Money::usd($p->unit_price),
                    'unit_price' => (float) $p->unit_price,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $productsCountQuery = Product::query();
        $this->applyProductBranchScope($productsCountQuery);
        $productsCount = $productsCountQuery->count();

        $saleLineRows = $this->normalizedSaleLineRowsFromOld();

        $clients = Client::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']);

        return view('sale_items.create', compact(
            'salesSession',
            'locations',
            'saleCatalog',
            'saleLineRows',
            'productsCount',
            'clients',
        ));
    }

    public function store(Request $request, SalesSession $salesSession): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        if (! $salesSession->isOpen()) {
            return back()->withErrors(['sale' => 'Impossible d’enregistrer une vente sur une session fermée.']);
        }

        $data = $request->validate([
            'payment_type' => ['required', 'in:cash,credit'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'apply_sale_discount' => ['nullable', 'boolean'],
            'sale_discount_amount' => [
                Rule::requiredIf(fn () => $request->boolean('apply_sale_discount')),
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $pendingDiscountAfterSave = false;

        try {
            DB::transaction(function () use ($request, $salesSession, $data, &$pendingDiscountAfterSave) {
                $paymentType = $data['payment_type'];

                $clientName = trim((string) ($data['client_name'] ?? ''));
                $clientPhone = trim((string) ($data['client_phone'] ?? ''));

                $clientId = null;
                if ($paymentType === 'credit') {
                    if ($clientName === '') {
                        throw new RuntimeException('Le nom du client est obligatoire pour une vente à crédit.');
                    }

                    $client = Client::query()->firstOrCreate(['name' => $clientName]);
                    if ($clientPhone !== '') {
                        $client->update(['phone' => $clientPhone]);
                    }
                    $clientId = $client->id;
                }

                $saleReference = $this->nextSaleReference();
                $sale = Sale::create([
                    'reference' => $saleReference,
                    'sales_session_id' => $salesSession->id,
                    'user_id' => $request->user()->id,
                    'payment_type' => $paymentType,
                    'client_id' => $clientId,
                    'total_amount' => 0,
                    'sold_at' => now(),
                ]);

                $saleTotal = '0.00';
                foreach ($data['items'] as $row) {
                    $product = Product::query()->lockForUpdate()->findOrFail((int) $row['product_id']);
                    $location = Location::query()->lockForUpdate()->findOrFail((int) $row['location_id']);
                    if ((int) $location->branch_id !== (int) $salesSession->branch_id) {
                        throw new RuntimeException('Un des emplacements n’appartient pas à la branche de la session.');
                    }

                    $qty = (int) $row['quantity'];
                    Stock::modifyQuantity($product->id, $location->id, -$qty);

                    $unit = (string) $product->unit_price;
                    $lineTotal = bcmul($unit, (string) $qty, 2);
                    $saleTotal = bcadd($saleTotal, $lineTotal, 2);

                    $saleItem = SaleItem::create([
                        'reference' => null,
                        'sale_id' => $sale->id,
                        'sales_session_id' => $salesSession->id,
                        'location_id' => $location->id,
                        'product_id' => $product->id,
                        'user_id' => $request->user()->id,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => $lineTotal,
                        'discount_amount' => '0.00',
                        'payment_type' => $paymentType,
                        'client_id' => $clientId,
                    ]);

                    StockMovement::create([
                        'type' => 'exit',
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'from_location_id' => $location->id,
                        'to_location_id' => null,
                        'user_id' => $request->user()->id,
                        'sales_session_id' => $salesSession->id,
                        'sale_item_id' => $saleItem->id,
                        'notes' => 'Vente '.$saleReference.' — session #'.$salesSession->id,
                    ]);
                }

                $subtotal = $saleTotal;
                $applyDiscount = $request->boolean('apply_sale_discount');
                $isAdmin = (bool) $request->user()->is_admin;

                if ($applyDiscount) {
                    $discountStr = number_format((float) ($data['sale_discount_amount'] ?? 0), 2, '.', '');
                    if (bccomp($discountStr, '0', 2) <= 0) {
                        throw new RuntimeException('Indiquez un montant de remise supérieur à zéro.');
                    }
                    if (bccomp($discountStr, $subtotal, 2) > 0) {
                        throw new RuntimeException('La remise ne peut pas dépasser le sous-total des lignes.');
                    }

                    if ($isAdmin) {
                        $finalTotal = bcsub($subtotal, $discountStr, 2);
                        $sale->update([
                            'subtotal_amount' => $subtotal,
                            'sale_status' => Sale::STATUS_CONFIRMED,
                            'discount_requested_amount' => null,
                            'discount_requested_by' => null,
                            'discount_requested_at' => null,
                            'discount_amount' => $discountStr,
                            'discount_approved_by' => $request->user()->id,
                            'discount_approved_at' => now(),
                            'total_amount' => $finalTotal,
                        ]);
                    } else {
                        $pendingDiscountAfterSave = true;
                        $sale->update([
                            'subtotal_amount' => $subtotal,
                            'sale_status' => Sale::STATUS_PENDING_DISCOUNT,
                            'discount_requested_amount' => $discountStr,
                            'discount_requested_by' => $request->user()->id,
                            'discount_requested_at' => now(),
                            'discount_amount' => null,
                            'discount_approved_by' => null,
                            'discount_approved_at' => null,
                            'total_amount' => $subtotal,
                        ]);
                    }
                } else {
                    $sale->update([
                        'subtotal_amount' => $subtotal,
                        'sale_status' => Sale::STATUS_CONFIRMED,
                        'discount_requested_amount' => null,
                        'discount_requested_by' => null,
                        'discount_requested_at' => null,
                        'discount_amount' => null,
                        'discount_approved_by' => null,
                        'discount_approved_at' => null,
                        'total_amount' => $subtotal,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        $success = $pendingDiscountAfterSave
            ? 'Vente enregistrée. La remise est en attente d’approbation par un administrateur avant d’être appliquée au total.'
            : 'Vente enregistrée et stock mis à jour.';

        return redirect()
            ->route('sales-sessions.show', $salesSession)
            ->with('success', $success);
    }

    /**
     * @return list<array{department_id: string, product_id: string, location_id: string, quantity: int}>
     */
    private function normalizedSaleLineRowsFromOld(): array
    {
        $old = old('items');
        if (! is_array($old) || $old === []) {
            return [[
                'department_id' => '',
                'product_id' => '',
                'location_id' => '',
                'quantity' => 1,
            ]];
        }

        return collect($old)->map(function (array $row) {
            $deptId = isset($row['department_id']) ? (string) $row['department_id'] : '';
            if ($deptId === '' && ! empty($row['product_id'])) {
                $deptId = (string) (Product::query()->whereKey((int) $row['product_id'])->value('department_id') ?? '');
            }

            return [
                'department_id' => $deptId,
                'product_id' => isset($row['product_id']) ? (string) $row['product_id'] : '',
                'location_id' => isset($row['location_id']) ? (string) $row['location_id'] : '',
                'quantity' => (int) ($row['quantity'] ?? 1),
            ];
        })->all();
    }

    private function nextSaleReference(): string
    {
        $now = Date::now();
        $saleDate = $now->toDateString();

        $counter = DB::table('sale_reference_counters')
            ->where('sale_date', $saleDate)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            DB::table('sale_reference_counters')->insert([
                'sale_date' => $saleDate,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $counter = (object) ['last_number' => 0];
        }

        $nextNumber = ((int) $counter->last_number) + 1;

        DB::table('sale_reference_counters')
            ->where('sale_date', $saleDate)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => $now,
            ]);

        return sprintf('%s-%06d', $now->format('ymd'), $nextNumber);
    }
}
