<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WholesaleProductController;
use App\Http\Controllers\Api\RetailPosController;
use App\Http\Controllers\Api\WholesalePosController;
use App\Http\Controllers\Api\RetailSaleController;
use App\Http\Controllers\Api\WholesaleSaleController;
use App\Http\Controllers\Api\ReversalController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ExpenseController;

// Public
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Retail Inventory (Admin only)
    Route::apiResource('products', ProductController::class);
    Route::patch('/products/{product}/restock', [ProductController::class, 'restock']);

    // Wholesale Inventory (Admin only)
    Route::apiResource('wholesale-products', WholesaleProductController::class);
    Route::patch('/wholesale-products/{wholesaleProduct}/restock', [WholesaleProductController::class, 'restock']);

    // POS
    Route::post('/pos/retail/checkout', [RetailPosController::class, 'checkout']);
    Route::post('/pos/wholesale/checkout', [WholesalePosController::class, 'checkout']);

    // Sales History
    Route::get('/sales/retail', [RetailSaleController::class, 'index']);
    Route::get('/sales/retail/{id}', [RetailSaleController::class, 'show']);
    Route::get('/sales/wholesale', [WholesaleSaleController::class, 'index']);
    Route::get('/sales/wholesale/{id}', [WholesaleSaleController::class, 'show']);
    Route::post('/sales/wholesale/{id}/pay', [WholesaleSaleController::class, 'payDebt']);

    // Reversals (Admin)
    Route::get('/reversals', [ReversalController::class, 'index']);
    Route::post('/reversals', [ReversalController::class, 'store']);

    // Clients (Admin)
    Route::apiResource('clients', ClientController::class);

    // Workers (Admin)
    Route::apiResource('workers', WorkerController::class)->except(['show']);
    Route::patch('/workers/{worker}/status', [WorkerController::class, 'updateStatus']);

    // Reports (Admin)
    Route::get('/reports/financial', [ReportController::class, 'financial']);

    // Audit Logs (Admin)
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

    // Expenses (all users can create/view own; admins can view all and delete)
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::get('/expenses/today-stats', [ExpenseController::class, 'stats']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
});
