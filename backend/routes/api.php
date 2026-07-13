<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

// Public: a newly invited user sets their password from the emailed link. No
// auth — the token in the request is what authorizes it.
Route::post('/set-password', [InvitationController::class, 'setPassword']);

Route::middleware(['auth:sanctum', EnsurePasswordChanged::class])->group(function () {
    Route::get('/user', fn (Request $request) => UserResource::make($request->user()));

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Self-service: any authenticated user (including one still flagged
    // must_change_password, who has no roles yet) can set their own password.
    Route::post('/change-password', [PasswordController::class, 'update']);

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view');

    Route::post('/customers/ask', [CustomerController::class, 'ask'])
        ->middleware('permission:customers.view');

    // The signed-in rep's own dashboard (their book of business). customers.view
    // is the module gate; the data itself is scoped per-rep in the controller.
    Route::get('/dashboard/my-book', [DashboardController::class, 'myBook'])
        ->middleware('permission:customers.view');

    Route::middleware(['permission:customers.create', 'permission:customers.update'])->group(function () {
        Route::post('/customers/preview', [CustomerController::class, 'preview']);
        Route::post('/customers/confirm', [CustomerController::class, 'confirm']);
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::put('/users/{user}/roles', [UserController::class, 'updateRoles']);
        Route::patch('/users/{user}/active', [UserController::class, 'setActive']);
        Route::post('/users/{user}/resend-invitation', [UserController::class, 'resendInvitation']);

        // Rep assignment is admin-only, so it lives in the roles.manage group
        // (sales_representative already holds customers.update for the CSV
        // confirm flow, so that permission wouldn't distinguish admin here).
        // Bulk route first: a literal path before the {customer} one.
        Route::patch('/customers/assign-representative', [CustomerController::class, 'assignRepresentativeBulk']);
        Route::patch('/customers/{customer}/representative', [CustomerController::class, 'assignRepresentative']);
    });

});

