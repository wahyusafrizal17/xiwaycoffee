<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MenuDisplayController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitShareController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\MenuController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WasteController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/menu', MenuController::class)->name('site.menu');
Route::get('/display', MenuDisplayController::class)->name('menu.display');
Route::get('/display/focus', [MenuDisplayController::class, 'focus'])->name('menu.display.focus');
Route::get('/invite/{slug}', [InviteController::class, 'show'])->name('invites.show');
Route::get('/pos/{order}/invoice.pdf', [PosController::class, 'invoicePdf'])
    ->middleware('signed')
    ->name('pos.invoice.pdf');

Route::redirect('/login', '/management/login');
Route::redirect('/forgot-password', '/management/forgot-password');

Route::prefix('management')->middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:8,1');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::middleware(['auth', 'active', 'outlet'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');

    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/display-focus', [MenuDisplayController::class, 'setFocus'])->name('display.focus');
        Route::get('/held', [PosController::class, 'held'])->name('held');
        Route::post('/draft', [PosController::class, 'draft'])->name('draft');
        Route::post('/{order}/items', [PosController::class, 'addItem'])->name('items.store');
        Route::put('/{order}/items/{item}', [PosController::class, 'updateItem'])->name('items.update');
        Route::delete('/{order}/items/{item}', [PosController::class, 'removeItem'])->name('items.destroy');
        Route::post('/{order}/discount', [PosController::class, 'discount'])->name('discount');
        Route::post('/{order}/points', [PosController::class, 'points'])->name('points');
        Route::post('/{order}/customer', [PosController::class, 'customer'])->name('customer');
        Route::post('/{order}/submit', [PosController::class, 'submit'])->name('submit');
        Route::post('/{order}/transfer', [PosController::class, 'transfer'])->name('transfer');
        Route::post('/{order}/hold', [PosController::class, 'hold'])->name('hold');
        Route::get('/{order}/recall', [PosController::class, 'recall'])->name('recall');
        Route::post('/{order}/checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::post('/{order}/invoice-whatsapp', [PosController::class, 'sendInvoiceWhatsapp'])->name('invoice.whatsapp');
        Route::post('/{order}/cancel', [PosController::class, 'cancel'])->name('cancel');
        Route::get('/{order}/receipt', [PosController::class, 'receipt'])->name('receipt');
        Route::get('/{order}/ticket/{station}', [PosController::class, 'ticket'])->name('ticket');
    });

    Route::get('/kitchen', [CheckerController::class, 'kitchen'])->name('kitchen.index');
    Route::get('/bar', [CheckerController::class, 'bar'])->name('bar.index');
    Route::post('/order-items/{item}/status', [CheckerController::class, 'updateItem'])->name('order-items.status');
    Route::get('/print-jobs', [CheckerController::class, 'pendingJobs'])->name('print-jobs.index');
    Route::post('/print-jobs/{order}', [CheckerController::class, 'ackJob'])->name('print-jobs.ack');
    Route::get('/alerts/low-stock', [AlertController::class, 'lowStock'])->name('alerts.low-stock');

    Route::get('/orders/kanban', fn () => redirect()->route('orders.index'))->name('orders.kanban');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/status', fn ($order) => redirect()->route('orders.show', $order));
    Route::post('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');

    Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
    Route::get('/tables/live', [TableController::class, 'live'])->name('tables.live');
    Route::post('/tables', [TableController::class, 'store'])->name('tables.store');
    Route::get('/tables/{table}', [TableController::class, 'show'])->name('tables.show');
    Route::put('/tables/{table}', [TableController::class, 'update'])->name('tables.update');
    Route::delete('/tables/{table}', [TableController::class, 'destroy'])->name('tables.destroy');
    Route::post('/tables/{table}/move', [TableController::class, 'move'])->name('tables.move');
    Route::post('/tables/transfer', [TableController::class, 'transfer'])->name('tables.transfer');
    Route::post('/tables/merge', [TableController::class, 'merge'])->name('tables.merge');
    Route::post('/tables/split', [TableController::class, 'split'])->name('tables.split');
    Route::post('/tables/reserve', [TableController::class, 'reserve'])->name('tables.reserve');

    Route::resource('products', ProductController::class)->except('show');
    Route::put('/products/{product}/options', [ProductController::class, 'updateOptions'])->name('products.options.update');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::get('/customers/template', [CustomerController::class, 'template'])->name('customers.template');
    Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import');
    Route::resource('customers', CustomerController::class);
    Route::post('/customers/{customer}/points', [CustomerController::class, 'adjustPoints'])->name('customers.points');
    Route::post('/customers/{customer}/rewards', [CustomerController::class, 'redeemReward'])->name('customers.rewards');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

    Route::get('/opnames', [StockOpnameController::class, 'index'])->name('opnames.index');
    Route::post('/opnames', [StockOpnameController::class, 'store'])->name('opnames.store');
    Route::get('/opnames/{opname}', [StockOpnameController::class, 'show'])->name('opnames.show');
    Route::put('/opnames/{opname}', [StockOpnameController::class, 'update'])->name('opnames.update');
    Route::post('/opnames/{opname}/finalize', [StockOpnameController::class, 'finalize'])->name('opnames.finalize');

    Route::get('/wastes', [WasteController::class, 'index'])->name('wastes.index');
    Route::post('/wastes', [WasteController::class, 'store'])->name('wastes.store');

    Route::get('/transfers', [StockTransferController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/create', [StockTransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [StockTransferController::class, 'store'])->name('transfers.store');
    Route::get('/transfers/{transfer}', [StockTransferController::class, 'show'])->name('transfers.show');
    Route::post('/transfers/{transfer}/request', [StockTransferController::class, 'request'])->name('transfers.request');
    Route::post('/transfers/{transfer}/approve', [StockTransferController::class, 'approve'])->name('transfers.approve');
    Route::post('/transfers/{transfer}/ship', [StockTransferController::class, 'ship'])->name('transfers.ship');
    Route::post('/transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');

    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    Route::get('/production/create', [ProductionController::class, 'create'])->name('production.create');
    Route::post('/production', [ProductionController::class, 'store'])->name('production.store');
    Route::get('/production/{production}', [ProductionController::class, 'show'])->name('production.show');
    Route::post('/production/{production}/start', [ProductionController::class, 'start'])->name('production.start');
    Route::post('/production/{production}/complete', [ProductionController::class, 'complete'])->name('production.complete');
    Route::post('/production/{production}/cancel', [ProductionController::class, 'cancel'])->name('production.cancel');

    Route::resource('boms', BomController::class)->except('destroy');
    Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');

    Route::get('/marketing/discounts', [MarketingController::class, 'discounts'])->name('marketing.discounts');
    Route::post('/marketing/discounts', [MarketingController::class, 'storeDiscount'])->name('marketing.discounts.store');
    Route::put('/marketing/discounts/{discount}', [MarketingController::class, 'updateDiscount'])->name('marketing.discounts.update');
    Route::delete('/marketing/discounts/{discount}', [MarketingController::class, 'destroyDiscount'])->name('marketing.discounts.destroy');
    Route::post('/marketing/discounts/{discount}/toggle', [MarketingController::class, 'toggleDiscount'])->name('marketing.discounts.toggle');
    Route::get('/marketing/bundles', [MarketingController::class, 'bundles'])->name('marketing.bundles');
    Route::post('/marketing/bundles', [MarketingController::class, 'storeBundle'])->name('marketing.bundles.store');
    Route::post('/marketing/bundles/{bundle}/toggle', [MarketingController::class, 'toggleBundle'])->name('marketing.bundles.toggle');

    Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::get('/loyalty/rewards', [MarketingController::class, 'rewards'])->name('loyalty.rewards');
    Route::post('/loyalty/rewards', [MarketingController::class, 'storeReward'])->name('loyalty.rewards.store');
    Route::post('/loyalty/rewards/{reward}/toggle', [MarketingController::class, 'toggleReward'])->name('loyalty.rewards.toggle');

    Route::get('/printers', [PrinterController::class, 'index'])->name('printers.index');
    Route::post('/printers', [PrinterController::class, 'store'])->name('printers.store');
    Route::put('/printers/{printer}', [PrinterController::class, 'update'])->name('printers.update');
    Route::delete('/printers/{printer}', [PrinterController::class, 'destroy'])->name('printers.destroy');

    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/products', [ReportController::class, 'products'])->name('reports.products');
    Route::get('/reports/categories', [ReportController::class, 'categories'])->name('reports.categories');
    Route::get('/reports/promo', [ReportController::class, 'promo'])->name('reports.promo');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/reports/movements', [ReportController::class, 'movements'])->name('reports.movements');
    Route::get('/reports/production', [ReportController::class, 'production'])->name('reports.production');
    Route::get('/reports/customers', [ReportController::class, 'customers'])->name('reports.customers');
    Route::get('/reports/waste', [ReportController::class, 'waste'])->name('reports.waste');
    Route::get('/reports/laba-rugi', [ReportController::class, 'labaRugi'])->name('reports.laba-rugi');
    Route::get('/reports/expenses', [ProfitShareController::class, 'expenses'])->name('reports.expenses');
    Route::post('/reports/expenses', [ProfitShareController::class, 'store'])->name('reports.expenses.store');
    Route::get('/reports/profit', [ProfitShareController::class, 'profit'])->name('reports.profit');
    Route::get('/reports/setoran', [ProfitShareController::class, 'setoran'])->name('reports.setoran');
    Route::post('/reports/setoran', [ProfitShareController::class, 'storeSetoran'])->name('reports.setoran.store');

    Route::get('/keuangan/rekening', [BankAccountController::class, 'index'])->name('bank.index');
    Route::get('/keuangan/setoran-kas', [BankAccountController::class, 'createDeposit'])->name('bank.deposits.create');
    Route::post('/keuangan/setoran-kas', [BankAccountController::class, 'storeDeposit'])->name('bank.deposits.store');
    Route::post('/keuangan/rekening/koreksi', [BankAccountController::class, 'storeAdjustment'])->name('bank.adjustments.store');

    Route::get('/investors', [InvestorController::class, 'index'])->name('investors.index');
    Route::post('/investors', [InvestorController::class, 'store'])->name('investors.store');
    Route::post('/investors/topup', [InvestorController::class, 'topup'])->name('investors.topup');
    Route::post('/investors/target', [InvestorController::class, 'storeTarget'])->name('investors.target');

    Route::get('/invites', [InviteController::class, 'index'])->name('invites.index');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
    Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
});
