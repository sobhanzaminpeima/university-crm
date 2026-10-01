<?php

use App\Http\Controllers\PortalController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::post('/portal/login', [PortalController::class, 'login']);
Route::get('/saas/plans', [LandingController::class, 'plansApi']);

Route::middleware(['auth.jwt', 'tenant'])->group(function (): void {
    Route::get('/portal/dashboard', [PortalController::class, 'dashboard']);
    Route::get('/portal/universities', [PortalController::class, 'universities']);
    Route::post('/portal/documents/upload', [PortalController::class, 'uploadDocument']);
});
