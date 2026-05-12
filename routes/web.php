<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashVoucherController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PosShiftController;
use App\Http\Controllers\PosTerminalController;
use App\Http\Controllers\PosTerminalWorkspaceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleEntryController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\SalesOverviewController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\UserManagementController;
use App\Models\Branch;
use App\Models\Department;
use App\Models\PosTerminal;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('products/export/pdf', [ProductController::class, 'exportPdf'])->name('products.export.pdf');
    Route::get('products/export/excel', [ProductController::class, 'exportExcel'])->name('products.export.excel');
    Route::get('products/import/sample', [ProductController::class, 'importSample'])->name('products.import.sample');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::resource('products', ProductController::class)->except(['show']);

    Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::get('stocks/{stock}/edit', [StockController::class, 'edit'])->whereNumber('stock')->name('stocks.edit');
    Route::patch('stocks/{stock}', [StockController::class, 'update'])->whereNumber('stock')->name('stocks.update');

    Route::resource('stock-movements', StockMovementController::class)->only(['index', 'create', 'store']);

    Route::get('stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
    Route::get('stock-transfers/{stock_transfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show')->whereNumber('stock_transfer');

    Route::middleware('stock_transfer')->group(function () {
        Route::get('stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
    });
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->whereNumber('purchase_order')->name('purchase-orders.show');
    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->whereNumber('purchase_order')->name('purchase-orders.receive');

    Route::get('ventes', SalesOverviewController::class)->name('sales.overview');
    Route::get('caisse', [SaleEntryController::class, 'create'])->name('sales.entry');
    Route::get('caisse/shifts/closed', [PosShiftController::class, 'closed'])->name('pos-terminal.shifts.closed');
    Route::get('caisse/shifts/closed/{shift}', [PosShiftController::class, 'showClosed'])->name('pos-terminal.shifts.closed.show')->whereNumber('shift');
    Route::delete('caisse/shifts/closed/{shift}', [PosShiftController::class, 'destroyClosed'])->name('pos-terminal.shifts.closed.destroy')->whereNumber('shift');
    Route::post('caisse/shifts/closed/{shift}/push-accounting', [PosShiftController::class, 'pushClosedShiftToAccounting'])->name('pos-terminal.shifts.closed.push-accounting')->whereNumber('shift');
    Route::get('caisse/branches/{branch}/terminaux', [SaleItemController::class, 'chooseTerminal'])->name('sales.choose-terminal')->whereNumber('branch');
    Route::get('caisse/branches/{branch}/terminaux/{pos_terminal}', [PosTerminalWorkspaceController::class, 'show'])->name('pos-terminal.workspace')->whereNumber(['branch', 'pos_terminal']);
    Route::post('caisse/branches/{branch}/terminaux/{pos_terminal}/shifts/open', [PosShiftController::class, 'open'])->name('pos-terminal.shifts.open')->whereNumber(['branch', 'pos_terminal']);
    Route::get('caisse/branches/{branch}/terminaux/{pos_terminal}/shifts/close-review', [PosShiftController::class, 'confirmClose'])->name('pos-terminal.shifts.close-review')->whereNumber(['branch', 'pos_terminal']);
    Route::post('caisse/branches/{branch}/terminaux/{pos_terminal}/shifts/close', [PosShiftController::class, 'close'])->name('pos-terminal.shifts.close')->whereNumber(['branch', 'pos_terminal']);
    Route::get('caisse/branches/{branch}/terminaux/{pos_terminal}/departements', [SaleItemController::class, 'chooseDepartment'])->name('sales.choose-department')->whereNumber(['branch', 'pos_terminal']);
    Route::get('caisse/branches/{branch}/terminaux/{pos_terminal}/departements/{department}/nouvelle', [SaleItemController::class, 'create'])->name('sales.create')->whereNumber(['branch', 'pos_terminal', 'department']);
    Route::post('caisse/branches/{branch}/terminaux/{pos_terminal}/departements/{department}/ventes', [SaleItemController::class, 'store'])->name('sales.store')->whereNumber(['branch', 'pos_terminal', 'department']);

    Route::get('ventes/nouvelle', fn () => redirect()->route('sales.entry', [], 301));
    Route::get('branches/{branch}/sales/create', function (Branch $branch) {
        return redirect()->route('sales.choose-terminal', $branch);
    })->whereNumber('branch');
    Route::get('branches/{branch}/pos-terminals/{pos_terminal}', function (Branch $branch, PosTerminal $pos_terminal) {
        abort_unless((int) $pos_terminal->branch_id === (int) $branch->id, 404);

        return redirect()->route('pos-terminal.workspace', [$branch, $pos_terminal]);
    })->whereNumber(['branch', 'pos_terminal']);
    Route::get('branches/{branch}/pos-terminals/{pos_terminal}/departments', function (Branch $branch, PosTerminal $pos_terminal) {
        abort_unless((int) $pos_terminal->branch_id === (int) $branch->id, 404);

        return redirect()->route('sales.choose-department', [$branch, $pos_terminal]);
    })->whereNumber(['branch', 'pos_terminal']);
    Route::get('branches/{branch}/pos-terminals/{pos_terminal}/departments/{department}/sales/create', function (Branch $branch, PosTerminal $pos_terminal, Department $department) {
        abort_unless((int) $pos_terminal->branch_id === (int) $branch->id, 404);

        return redirect()->route('sales.create', [$branch, $pos_terminal, $department]);
    })->whereNumber(['branch', 'pos_terminal', 'department']);
    Route::get('branches/{branch}/sale-items/{sale_item}', [SaleItemController::class, 'show'])->name('sale-items.show');
    Route::get('branches/{branch}/sales/{sale}', [SaleController::class, 'show'])->name('sales.show')->whereNumber('sale');
    Route::get('branches/{branch}/sales/{sale}/print-large', [SaleController::class, 'printLarge'])->name('sales.print-large')->whereNumber('sale');
    Route::get('branches/{branch}/sales/{sale}/print-small', [SaleController::class, 'printSmall'])->name('sales.print-small')->whereNumber('sale');
    Route::post('branches/{branch}/sales/{sale}/approve-discount', [SaleController::class, 'approveDiscount'])->name('sales.approve-discount')->whereNumber('sale');
    Route::post('branches/{branch}/sales/{sale}/reject-discount', [SaleController::class, 'rejectDiscount'])->name('sales.reject-discount')->whereNumber('sale');
    Route::post('branches/{branch}/sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store')->whereNumber('sale');
    Route::post('branches/{branch}/sales/{sale}/confirm-paid', [SaleController::class, 'confirmPaid'])->name('sales.confirm-paid')->whereNumber('sale');

    Route::middleware('admin')->group(function () {
        Route::get('stocks/current-quantity', [StockController::class, 'currentQuantity'])->name('stocks.current-quantity');
        Route::post('stocks/adjustment', [StockController::class, 'applyAdjustment'])->name('stocks.adjustment');
        Route::resource('branches', BranchController::class);
        Route::resource('branches.pos-terminals', PosTerminalController::class)
            ->except(['show'])
            ->parameters(['pos-terminals' => 'pos_terminal']);
        Route::resource('branches.locations', LocationController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::resource('users', UserManagementController::class)->except(['show']);
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->whereNumber('purchase_order')->name('purchase-orders.edit');
        Route::patch('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->whereNumber('purchase_order')->name('purchase-orders.update');
        Route::get('parametre', [SettingController::class, 'edit'])->name('parametre.edit');
        Route::patch('parametre', [SettingController::class, 'update'])->name('parametre.update');

        Route::get('branches/{branch}/sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit')->whereNumber('sale');
        Route::patch('branches/{branch}/sales/{sale}', [SaleController::class, 'update'])->name('sales.update')->whereNumber('sale');
        Route::delete('branches/{branch}/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy')->whereNumber('sale');
        Route::post('bons-de-caisse/{cashVoucher}/approve', [CashVoucherController::class, 'approve'])->name('cash-vouchers.approve');
    });

    Route::middleware('accounting_or_cashier')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::post('clients/{client}/payments', [ClientController::class, 'storePayment'])->name('clients.payments.store');
        Route::get('bons-de-caisse', [CashVoucherController::class, 'index'])->name('cash-vouchers.index');
        Route::post('bons-de-caisse', [CashVoucherController::class, 'store'])->name('cash-vouchers.store');
        Route::get('bons-de-caisse/{cashVoucher}/comptabiliser', [CashVoucherController::class, 'createAccountingEntry'])->name('cash-vouchers.accounting.create');
        Route::post('bons-de-caisse/{cashVoucher}/comptabiliser', [CashVoucherController::class, 'storeAccountingEntry'])->name('cash-vouchers.accounting.store');
    });

    Route::middleware('accounting')->group(function () {
        Route::get('plan-comptable', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
        Route::post('plan-comptable', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
        Route::get('comptabilite', [AccountingController::class, 'index'])->name('accounting.index');
        Route::post('comptabilite/ecritures', [AccountingController::class, 'store'])->name('accounting.store');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
