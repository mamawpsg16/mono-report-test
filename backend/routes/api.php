<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        return [
            ...$user->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view');

    Route::post('/customers/ask', [CustomerController::class, 'ask'])
        ->middleware('permission:customers.view');

    Route::middleware(['permission:customers.create', 'permission:customers.update'])->group(function () {
        Route::post('/customers/preview', [CustomerController::class, 'preview']);
        Route::post('/customers/confirm', [CustomerController::class, 'confirm']);
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::get('/permissions', [PermissionController::class, 'index']);
    });

});

