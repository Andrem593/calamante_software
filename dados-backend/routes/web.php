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

Route::get('/', function () {
    return redirect('/admin');
});

// Panel Admin — solo accesible para superadmin, admin, supervisor
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('clients', ClientController::class);
    Route::resource('clients.branches', BranchController::class)->shallow();
    Route::post('clients/import', [ClientController::class, 'import'])->name('clients.import');

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
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/export-pdf', [OrderController::class, 'exportPdf'])->name('orders.export-pdf');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
});

Route::get('/login', function () {
    return inertia('Auth/Login');
})->name('login');

Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
