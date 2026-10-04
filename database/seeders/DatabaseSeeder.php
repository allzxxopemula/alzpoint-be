<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CooperativeSettingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// 1. Root API Endpoint (Mencegah Error 500 saat buka https://alzpoint-be.vercel.app/api)
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'AlzPoint API is running smoothly!',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// 2. Preflight OPTIONS Handling khusus Serverless Vercel
Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

// 3. Public Routes
Route::post('/login', [AuthController::class, 'login']);

// 4. Protected Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AdminProfileController::class, 'update']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/settings/cooperative', [CooperativeSettingController::class, 'show']);
    Route::put('/settings/cooperative', [CooperativeSettingController::class, 'update']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::delete('/categories/{categoryId}', [CategoryController::class, 'destroy']);

    Route::get('/discounts', [DiscountController::class, 'index']);
    Route::post('/discounts', [DiscountController::class, 'store']);
    Route::delete('/discounts/{discountId}', [DiscountController::class, 'destroy']);

    Route::get('/cashiers', [CashierController::class, 'index']);
    Route::post('/cashiers', [CashierController::class, 'store']);
    Route::put('/cashiers/{userId}', [CashierController::class, 'update']);
    Route::delete('/cashiers/{userId}', [CashierController::class, 'destroy']);

    Route::get('/customers', [CustomerController::class, 'index']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{productId}', [ProductController::class, 'update']);
    Route::patch('/products/{productId}', [ProductController::class, 'update']);
    Route::delete('/products/{productId}', [ProductController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{orderId}/status', [OrderController::class, 'updateStatus']);
});