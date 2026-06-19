<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SpecialPriceController;

Route::get('/', function () {
    return redirect('/admin');
});

// Panel Admin — solo accesible para superadmin, admin, supervisor
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('clients/search-json', [ClientController::class, 'searchJson'])->name('clients.search-json');
    Route::post('clients/merge', [ClientController::class, 'merge'])->name('clients.merge');
    Route::resource('clients', ClientController::class);
    Route::resource('clients.branches', BranchController::class)->shallow();
    Route::post('clients/import', [ClientController::class, 'import'])->name('clients.import');
    
    Route::resource('special-prices', SpecialPriceController::class)->only(['index', 'destroy']);
    Route::post('special-prices/import', [SpecialPriceController::class, 'import'])->name('special-prices.import');

    // Gestión de usuarios — solo superadmin y admin pueden manejar usuarios
    Route::resource('users', UserController::class)->middleware('admin:superadmin,admin');

    Route::resource('products', ProductController::class);
    Route::post('products/sync', [ProductController::class, 'sync'])->name('products.sync');
    Route::post('products/{product}/toggle-visibility', [ProductController::class, 'toggleVisibility'])->name('products.toggle-visibility');
    Route::post('clients/sync', [ClientController::class, 'sync'])->name('clients.sync');
    
    Route::group(['middleware' => 'admin:superadmin'], function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingController::class, 'store'])->name('settings.store');
    });

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/map', [OrderController::class, 'map'])->name('orders.map');
    Route::post('orders/bulk-deliver', [OrderController::class, 'bulkDeliver'])->name('orders.bulk-deliver');
    Route::get('orders/bulk-print', [OrderController::class, 'bulkPrint'])->name('orders.bulk-print');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::get('orders/{order}/export-pdf', [OrderController::class, 'exportPdf'])->name('orders.export-pdf');
    Route::post('orders/{order}/sync-contifico', [OrderController::class, 'syncContifico'])->name('orders.sync-contifico');
    Route::post('orders/{order}/authorize-sri', [OrderController::class, 'authorizeSri'])->name('orders.authorize-sri');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('reports/sellers', [ReportController::class, 'sellers'])->name('reports.sellers');
});

Route::get('/login', function () {
    return inertia('Auth/Login');
})->name('login');

Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
