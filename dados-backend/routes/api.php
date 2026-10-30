<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrderController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/app-version', [DashboardController::class, 'appVersion']);
Route::get('/orders/report-pdf', [OrderController::class, 'reportPdf']);
Route::get('/orders/{id}/pdf', [OrderController::class, 'pdf']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Búsqueda de clientes (debe ir antes del apiResource para no ser opacada)
    Route::get('/clients/search', [ClientController::class, 'search']);
    Route::get('/clients/{id}/branches', [ClientController::class, 'branches']);
    Route::post('/clients/{id}/branches/census', [ClientController::class, 'saveBranchCensus']);
    Route::post('/branches/census', [ClientController::class, 'saveBranchCensus']);
    Route::get('/clients/{id}/credit-status', [ClientController::class, 'getCreditStatus']);
    Route::apiResource('clients', ClientController::class)->only(['index', 'show']);

    Route::get('/equipments', [ClientController::class, 'getEquipments']);
    Route::get('/clients/{id}/equipments', [ClientController::class, 'getClientEquipments']);
    Route::post('/clients/{id}/equipments', [ClientController::class, 'syncClientEquipments']);

    Route::get('/products/categories', [ProductController::class, 'categories']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/deliver', [OrderController::class, 'markAsDelivered']);
    Route::post('/orders/{id}/invoiced', [OrderController::class, 'toggleInvoiced']);
    Route::get('/contifico/documento/{contifico_id}', [OrderController::class, 'getContificoDocument']);

    Route::get('/visits', [App\Http\Controllers\API\VisitController::class, 'index']);
    Route::post('/visits', [App\Http\Controllers\API\VisitController::class, 'store']);
});
