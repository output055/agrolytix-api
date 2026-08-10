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
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\SupabaseBackupController;

// Public
Route::post('/contact', [ContactController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Paystack webhook (must be public, before auth middleware)
Route::post('/webhooks/paystack', [SubscriptionController::class, 'webhook']);

// Paystack callback redirect (called by Paystack after payment)
Route::get('/subscription/callback', [SubscriptionController::class, 'callback']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Subscription status & initiate (NOT subscription-gated — user needs these to pay)
    Route::get('/subscription',          [SubscriptionController::class, 'status']);
    Route::post('/subscription/initiate', [SubscriptionController::class, 'initiate']);
});

// All other routes are subscription-gated
Route::middleware(['auth:sanctum', 'tenant', 'subscription'])->group(function () {

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
    Route::get('/reports/financial',          [ReportController::class, 'financial']);
    Route::get('/reports/revenue',            [ReportController::class, 'revenueReport']);
    Route::get('/reports/sales-insights',     [ReportController::class, 'salesInsights']);
    Route::get('/reports/expenses',           [ReportController::class, 'expenseReport']);
    Route::get('/reports/debt-analysis',      [ReportController::class, 'debtAnalysis']);
    Route::get('/reports/inventory-insights', [ReportController::class, 'inventoryInsights']);

    // Audit Logs (Admin)
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

    // Expenses (all users can create/view own; admins can view all and delete)
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::get('/expenses/today-stats', [ExpenseController::class, 'stats']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

    // Stock Transfers (Admin)
    Route::get('/stock-transfers/eligible-businesses', [StockTransferController::class, 'eligibleBusinesses']);
    Route::get('/stock-transfers/eligible-products', [StockTransferController::class, 'eligibleProducts']);
    Route::get('/stock-transfers', [StockTransferController::class, 'index']);
    Route::post('/stock-transfers', [StockTransferController::class, 'transfer']);

    // Branches (Admin)
    Route::get('/branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
    Route::post('/branches', [\App\Http\Controllers\Api\BranchController::class, 'store']);
    Route::put('/branches/{branch}', [\App\Http\Controllers\Api\BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [\App\Http\Controllers\Api\BranchController::class, 'destroy']);
});

// Super Admin Only
Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    Route::get('/super-admin/stats', [\App\Http\Controllers\Api\SuperAdminController::class, 'stats']);
    Route::get('/super-admin/businesses', [\App\Http\Controllers\Api\SuperAdminController::class, 'index']);
    Route::get('/super-admin/businesses/{id}/details', [\App\Http\Controllers\Api\SuperAdminController::class, 'businessDetails']);
    Route::put('/super-admin/businesses/{id}/subscription', [\App\Http\Controllers\Api\SuperAdminController::class, 'updateSubscription']);
    Route::post('/super-admin/businesses/{id}/suspend', [\App\Http\Controllers\Api\SuperAdminController::class, 'suspend']);
    Route::post('/super-admin/businesses/{id}/activate', [\App\Http\Controllers\Api\SuperAdminController::class, 'activate']);
    Route::post('/super-admin/businesses/{id}/extend-trial', [\App\Http\Controllers\Api\SuperAdminController::class, 'extendTrial']);
    Route::post('/super-admin/businesses/{id}/change-plan', [\App\Http\Controllers\Api\SuperAdminController::class, 'changePlan']);
    Route::get('/super-admin/payments', [\App\Http\Controllers\Api\SuperAdminController::class, 'payments']);
    Route::get('/super-admin/messages', [\App\Http\Controllers\Api\SuperAdminController::class, 'messages']);
    Route::patch('/super-admin/messages/{id}/read', [\App\Http\Controllers\Api\SuperAdminController::class, 'markMessageRead']);

    // Supabase mirror backup (super admin only)
    Route::post('/backups/supabase/run',    [SupabaseBackupController::class, 'run']);
    Route::get('/backups/supabase/latest',  [SupabaseBackupController::class, 'latest']);
});
