<?php

use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::prefix('imports')->group(function () {
        Route::get('/', [ImportController::class, 'index']);
        Route::post('/', [ImportController::class, 'store']);
        Route::post('/preview', [ImportController::class, 'preview']);
        Route::post('/{id}/confirm', [ImportController::class, 'confirm']);
        Route::get('/{id}', [ImportController::class, 'show']);
    });
});
