<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\SalesSessionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('locations', LocationController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);

    Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::get('stocks/{stock}/edit', [StockController::class, 'edit'])->name('stocks.edit');
    Route::patch('stocks/{stock}', [StockController::class, 'update'])->name('stocks.update');

    Route::resource('stock-movements', StockMovementController::class)->only(['index']);
    Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->whereNumber('purchase_order')->name('purchase-orders.show');
    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->whereNumber('purchase_order')->name('purchase-orders.receive');

    Route::resource('sales-sessions', SalesSessionController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('sales-sessions/{sales_session}/closure', [SalesSessionController::class, 'closure'])->name('sales-sessions.closure');
    Route::get('sales-sessions/{sales_session}/closure-recap', [SalesSessionController::class, 'closureRecap'])->name('sales-sessions.closure-recap');
    Route::post('sales-sessions/{sales_session}/close', [SalesSessionController::class, 'close'])->name('sales-sessions.close');
    Route::post('sales-sessions/{sales_session}/expenses', [SalesSessionController::class, 'storeExpense'])->name('sales-sessions.expenses.store');
    Route::delete('sales-sessions/{sales_session}/expenses/{expense}', [SalesSessionController::class, 'destroyExpense'])->name('sales-sessions.expenses.destroy');
    Route::get('sales-sessions/{sales_session}/sale-items/create', [SaleItemController::class, 'create'])->name('sale-items.create');
    Route::post('sales-sessions/{sales_session}/sale-items', [SaleItemController::class, 'store'])->name('sale-items.store');
    Route::get('sales-sessions/{sales_session}/sale-items/{sale_item}', [SaleItemController::class, 'show'])->name('sale-items.show');
    Route::get('sales-sessions/{sales_session}/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('sales-sessions/{sales_session}/sales/{sale}/print-large', [SaleController::class, 'printLarge'])->name('sales.print-large');
    Route::get('sales-sessions/{sales_session}/sales/{sale}/print-small', [SaleController::class, 'printSmall'])->name('sales.print-small');

    Route::middleware('admin')->group(function () {
        Route::resource('branches', BranchController::class)->except(['show']);
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::resource('users', UserManagementController::class)->except(['show']);
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->whereNumber('purchase_order')->name('purchase-orders.edit');
        Route::patch('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->whereNumber('purchase_order')->name('purchase-orders.update');
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::post('clients/{client}/payments', [ClientController::class, 'storePayment'])->name('clients.payments.store');
        Route::get('comptabilite', [AccountingController::class, 'index'])->name('accounting.index');
        Route::post('comptabilite/ecritures', [AccountingController::class, 'store'])->name('accounting.store');
        Route::get('parametre', [SettingController::class, 'edit'])->name('parametre.edit');
        Route::patch('parametre', [SettingController::class, 'update'])->name('parametre.update');
        Route::post('sales-sessions/{sales_session}/reopen', [SalesSessionController::class, 'reopen'])->name('sales-sessions.reopen');
        Route::delete('sales-sessions/{sales_session}', [SalesSessionController::class, 'destroy'])->name('sales-sessions.destroy');
        Route::post('sales-sessions/{sales_session}/sales/{sale}/approve-discount', [SaleController::class, 'approveDiscount'])->name('sales.approve-discount');
        Route::post('sales-sessions/{sales_session}/sales/{sale}/reject-discount', [SaleController::class, 'rejectDiscount'])->name('sales.reject-discount');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
