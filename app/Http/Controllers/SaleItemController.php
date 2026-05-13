<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class SaleItemController extends Controller
{
    use RespectsUserBranch;

    public function show(Branch $branch, SaleItem $saleItem): View
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $saleItem->branch_id === (int) $branch->id, 404);

        $saleItem->load([
            'product.department',
            'location',
            'user',
            'client',
            'branch',
        ]);

        return view('sale_items.show', compact('branch', 'saleItem'));
    }

    public function chooseTerminal(Branch $branch): View|RedirectResponse
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless(auth()->user()?->canAccessPosSales(), 403);

        $terminals = $this->posTerminalsForUser($branch);
        if ($terminals->isEmpty()) {
            abort(403, 'Aucun terminal POS accessible pour cette branche.');
        }

        $user = auth()->user();
        // Administrateurs / comptables : toujours afficher la liste des terminaux (sélection explicite).
        if ($terminals->count() === 1 && ! $user?->canBypassBranchScope()) {
            return redirect()->route('pos-terminal.workspace', [$branch, $terminals->first()]);
        }

        $canPickAnotherBranch = $this->branchesForUser()->count() > 1;
        $openTerminalIds = $this->openPosTerminalIds($terminals);
        $openIds = array_flip($openTerminalIds);

        return view('sale_entry.choose-terminal', compact('branch', 'terminals', 'canPickAnotherBranch', 'openIds'));
    }

    public function chooseDepartment(Branch $branch, PosTerminal $posTerminal): View|RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $openShift = $posTerminal->openShift();
        if ($openShift === null) {
            return redirect()
                ->route('pos-terminal.workspace', [$branch, $posTerminal])
                ->with('warning', 'Ouvrez une session de caisse avant de vendre.');
        }

        $pointOfSale = $posTerminal->location;
        abort_unless($pointOfSale !== null, 404);

        $departments = Department::query()
            ->whereHas('products', function ($q) use ($branch) {
                $this->applyProductBranchScope($q);
                $this->applyProductScopeForBranch($q, $branch);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $canPickAnotherBranch = $this->branchesForUser()->count() > 1;

        return view('sale_entry.choose-department', compact('branch', 'posTerminal', 'pointOfSale', 'departments', 'canPickAnotherBranch'));
    }

    public function create(Branch $branch, PosTerminal $posTerminal, Department $department): View|RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $openShift = $posTerminal->openShift();
        if ($openShift === null) {
            return redirect()
                ->route('pos-terminal.workspace', [$branch, $posTerminal])
                ->with('warning', 'Ouvrez une session de caisse avant de vendre.');
        }

        $pointOfSale = $posTerminal->location;
        abort_unless($pointOfSale !== null, 404);

        $products = Product::query()
            ->where('department_id', $department->id);
        $this->applyProductBranchScope($products);
        $this->applyProductScopeForBranch($products, $branch);
        $products = $products
            ->select(['id', 'department_id', 'name', 'unit_price'])
            ->orderBy('name')
            ->get();

        abort_if($products->isEmpty(), 404);

        $stockAtPos = Stock::query()
            ->where('location_id', $pointOfSale->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->pluck('quantity', 'product_id');

        $saleCatalog = [[
            'id' => $department->id,
            'name' => $department->name,
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => $p->name.' — '.Money::usd($p->unit_price),
                'unit_price' => (float) $p->unit_price,
                'stock_qty' => (int) ($stockAtPos[$p->id] ?? 0),
            ])->values()->all(),
        ]];

        $productsCount = $products->count();

        $clients = Client::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']);

        $saleLineRows = collect($this->normalizedSaleLineRowsFromOld())
            ->map(fn (array $r, int $i) => [...$r, '_key' => 'row-'.$i.'-'.substr(sha1((string) microtime(true).$i), 0, 8)])
            ->values()
            ->all();

        return view('sale_items.create', compact(
            'branch',
            'posTerminal',
            'pointOfSale',
            'department',
            'saleCatalog',
            'saleLineRows',
            'productsCount',
            'clients',
        ));
    }

    public function store(Request $request, Branch $branch, PosTerminal $posTerminal, Department $department): RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $pointOfSale = $posTerminal->location;
        abort_unless($pointOfSale !== null, 404);

        $openShift = $posTerminal->openShift();
        if ($openShift === null) {
            return redirect()
                ->route('pos-terminal.workspace', [$branch, $posTerminal])
                ->with('warning', 'Session de caisse fermée ou non ouverte.');
        }

        $departmentId = (int) $department->id;

        $data = $request->validate([
            'customer_type' => ['required', 'in:walkin,dealer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.department_id' => ['required', 'integer', Rule::in([$departmentId])],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'apply_sale_discount' => ['sometimes', 'boolean'],
            'sale_discount_amount' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->has('apply_sale_discount')
                        && filter_var($request->input('apply_sale_discount'), FILTER_VALIDATE_BOOLEAN);
                }),
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $pendingDiscountAfterSave = false;

        try {
            DB::transaction(function () use ($request, $branch, $departmentId, $pointOfSale, $posTerminal, $openShift, $data, &$pendingDiscountAfterSave) {
                $openShift = PosShift::query()
                    ->whereKey($openShift->id)
                    ->where('pos_terminal_id', $posTerminal->id)
                    ->whereNull('closed_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                $customerType = $data['customer_type'];
                $paymentType = 'cash';

                $clientName = trim((string) ($data['client_name'] ?? ''));
                $clientPhone = trim((string) ($data['client_phone'] ?? ''));
                $amountPaid = number_format((float) ($data['amount_paid'] ?? 0), 2, '.', '');

                $clientId = null;
                $saleClientName = null;
                $saleClientPhone = null;

                if ($customerType === 'dealer') {
                    if ($clientName === '') {
                        throw new RuntimeException('Le nom du dealer est obligatoire.');
                    }

                    $client = Client::query()->firstOrCreate(['name' => $clientName]);
                    if ($clientPhone !== '') {
                        $client->update(['phone' => $clientPhone]);
                    }
                    $clientId = $client->id;
                } else {
                    $saleClientName = $clientName !== '' ? $clientName : null;
                    $saleClientPhone = $clientPhone !== '' ? $clientPhone : null;
                }

                $saleReference = $this->nextSaleReference();
                $sale = Sale::create([
                    'reference' => $saleReference,
                    'branch_id' => $branch->id,
                    'pos_shift_id' => $openShift->id,
                    'user_id' => $request->user()->id,
                    'payment_type' => $paymentType,
                    'client_id' => $clientId,
                    'client_name' => $saleClientName,
                    'client_phone' => $saleClientPhone,
                    'total_amount' => 0,
                    'amount_paid' => 0,
                    'balance_amount' => 0,
                    'payment_status' => Sale::PAYMENT_STATUS_FULLY_PAID,
                    'sold_at' => now(),
                ]);

                $saleTotal = '0.00';
                foreach ($data['items'] as $row) {
                    $product = Product::query()
                        ->lockForUpdate()
                        ->whereKey((int) $row['product_id'])
                        ->where('department_id', $departmentId)
                        ->where(function ($q) use ($branch) {
                            $this->applyProductBranchScope($q);
                            $this->applyProductScopeForBranch($q, $branch);
                        })
                        ->firstOrFail();

                    $qty = (int) $row['quantity'];
                    Stock::modifyQuantity($product->id, $pointOfSale->id, -$qty);

                    $unit = (string) $product->unit_price;
                    $lineTotal = bcmul($unit, (string) $qty, 2);
                    $saleTotal = bcadd($saleTotal, $lineTotal, 2);

                    $saleItem = SaleItem::create([
                        'reference' => null,
                        'sale_id' => $sale->id,
                        'branch_id' => $branch->id,
                        'location_id' => $pointOfSale->id,
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
                        'from_location_id' => $pointOfSale->id,
                        'to_location_id' => null,
                        'user_id' => $request->user()->id,
                        'sale_item_id' => $saleItem->id,
                        'notes' => 'Vente '.$saleReference.' — '.$branch->name,
                    ]);
                }

                $subtotal = $saleTotal;
                $applyDiscount = $request->has('apply_sale_discount')
                    && filter_var($request->input('apply_sale_discount'), FILTER_VALIDATE_BOOLEAN);
                $isAdmin = $request->user()->isAdmin();
                $dueTotal = $subtotal;

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
                        $dueTotal = $finalTotal;
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
                            'amount_paid' => $finalTotal,
                            'balance_amount' => '0.00',
                            'payment_status' => Sale::PAYMENT_STATUS_FULLY_PAID,
                        ]);
                    } else {
                        $pendingDiscountAfterSave = true;
                        $finalTotal = bcsub($subtotal, $discountStr, 2);
                        $dueTotal = $finalTotal;
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
                            'amount_paid' => $subtotal,
                            'balance_amount' => '0.00',
                            'payment_status' => Sale::PAYMENT_STATUS_FULLY_PAID,
                        ]);
                    }
                } else {
                    $dueTotal = $subtotal;
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
                        'amount_paid' => $subtotal,
                        'balance_amount' => '0.00',
                        'payment_status' => Sale::PAYMENT_STATUS_FULLY_PAID,
                    ]);
                }

                if ($customerType === 'dealer') {
                    if (bccomp($amountPaid, $dueTotal, 2) === 1) {
                        throw new RuntimeException('Le total payé ne peut pas dépasser le montant total.');
                    }

                    $computedBalance = bcsub($dueTotal, $amountPaid, 2);
                    if (bccomp($computedBalance, '0.00', 2) === -1) {
                        throw new RuntimeException('Le solde ne peut pas être négatif.');
                    }

                    // Solde impayé : dette client (lignes crédit) + paiement enregistré pour l’encaissement.
                    if (bccomp($computedBalance, '0.00', 2) === 1) {
                        $status = bccomp($amountPaid, '0.00', 2) === 1
                            ? Sale::PAYMENT_STATUS_PARTIALLY_PAID
                            : Sale::PAYMENT_STATUS_NOT_PAID;
                        $sale->update([
                            'payment_type' => 'credit',
                            'amount_paid' => $amountPaid,
                            'balance_amount' => $computedBalance,
                            'payment_status' => $status,
                        ]);
                        SaleItem::query()
                            ->where('sale_id', $sale->id)
                            ->update(['payment_type' => 'credit', 'client_id' => $clientId]);
                        $sale->recordInitialPosPaymentIfNeeded($request->user());
                    } else {
                        $sale->update([
                            'payment_type' => 'cash',
                            'amount_paid' => $dueTotal,
                            'balance_amount' => '0.00',
                            'payment_status' => Sale::PAYMENT_STATUS_FULLY_PAID,
                        ]);
                        SaleItem::query()
                            ->where('sale_id', $sale->id)
                            ->update(['payment_type' => 'cash']);
                    }
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        $success = $pendingDiscountAfterSave
            ? 'Vente enregistrée. La remise est en attente d’approbation par un administrateur avant d’être appliquée au total.'
            : 'Vente enregistrée et stock mis à jour.';

        return redirect()
            ->route('pos-terminal.workspace', [$branch, $posTerminal])
            ->with('success', $success);
    }

    /**
     * @return list<array{department_id: string, product_id: string, quantity: int}>
     */
    private function normalizedSaleLineRowsFromOld(): array
    {
        $old = old('items');
        if (! is_array($old) || $old === []) {
            return [];
        }

        return collect($old)->map(function (array $row) {
            $productIdRaw = $row['product_id'] ?? null;
            $hasProductId = filled($productIdRaw);

            $deptId = filled($row['department_id'] ?? null) ? (string) $row['department_id'] : '';
            if ($deptId === '' && $hasProductId) {
                $deptId = (string) (Product::query()->whereKey((int) $productIdRaw)->value('department_id') ?? '');
            }

            return [
                'department_id' => $deptId,
                'product_id' => $hasProductId ? (string) $productIdRaw : '',
                'quantity' => (int) ($row['quantity'] ?? 1),
            ];
        })->all();
    }

    /**
     * Global unique sale code: SL00001, SL00002, … (atomic, transaction-safe).
     */
    private function nextSaleReference(): string
    {
        return (string) DB::transaction(function () {
            $row = DB::table('sale_code_sequences')->where('id', 1)->lockForUpdate()->first();

            if (! $row) {
                DB::table('sale_code_sequences')->insert([
                    'id' => 1,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $last = 0;
            } else {
                $last = (int) $row->last_number;
            }

            $next = $last + 1;

            DB::table('sale_code_sequences')->where('id', 1)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return sprintf('SL%05d', $next);
        });
    }
}
