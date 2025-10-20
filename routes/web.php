<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/audit');
})->name('home');

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('auth:user_system')->group(function () {
    Route::get('/audit', [AuditController::class, 'index'])->name('audit');
    Route::post('/update-tenant', [AuthController::class, 'updateTenant'])->name('update.tenant');
});
