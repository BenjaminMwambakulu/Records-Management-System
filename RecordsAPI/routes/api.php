<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetLoanController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\FinancialCategoryController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MemberImportController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Webhooks\LogtoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/logto', [LogtoWebhookController::class, 'handleWebhook'])
    ->middleware('throttle:api-sensitive');

Route::post('v1/payments/callback', [PaymentController::class, 'callback'])
    ->middleware('throttle:api-sensitive');

Route::get('documents/versions/{version}/file', [DocumentController::class, 'downloadVersionFile'])
    ->name('documents.versions.file');

// ─── Public routes (no auth required) ───
Route::prefix('v1')->middleware('throttle:guest')->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);

    Route::get('documents', [DocumentController::class, 'index']);
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    Route::get('documents/{document}/versions', [DocumentController::class, 'listVersions']);
    Route::get('documents/{document}/versions/{version}', [DocumentController::class, 'showVersion']);
});

// ─── Authenticated routes ───
Route::prefix('v1')->middleware(['auth:logto', 'throttle:api'])->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::get('me/permissions', [MeController::class, 'permissions']);
    Route::patch('me', [MeController::class, 'update']);
    Route::post('me/avatar', [MeController::class, 'uploadAvatar']);

    Route::get('events/{event}/attendances', [EventController::class, 'attendances']);
    Route::post('events/{event}/register', [EventController::class, 'register']);
    Route::delete('events/{event}/register', [EventController::class, 'cancelRegistration']);
    Route::get('events/{event}/registration', [EventController::class, 'checkRegistration']);

    // Payments
    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware(['throttle:api-write']);
    Route::post('payments/mobile-money', [PaymentController::class, 'storeMobileMoney'])
        ->middleware(['throttle:api-write']);
    Route::get('payments/{payment}', [PaymentController::class, 'show'])
        ->middleware(['throttle:api-read']);
    Route::get('payments/{payment}/verify', [PaymentController::class, 'verify'])
        ->middleware(['throttle:api-read']);
    Route::get('my-payments', [PaymentController::class, 'myPayments'])
        ->middleware(['throttle:api-read']);

    // ─── Routes restricted from members ───
    Route::middleware('deny_member')->group(function () {
        // Dashboard
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware(['throttle:api-read', 'permission:dashboard.view,logto']);

        // Activity logs
        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:activity_logs.view,logto']);

        // Members management
        Route::get('members', [MemberController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::post('members', [MemberController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:members.create,logto']);
        Route::get('members/{member}', [MemberController::class, 'show'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::put('members/{member}', [MemberController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:members.update,logto']);
        Route::delete('members/{member}', [MemberController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:members.delete,logto']);
        Route::post('members/import', [MemberImportController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:members.create,logto']);
        Route::get('members/imports/template', [MemberImportController::class, 'template'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::get('members/imports/{import}', [MemberImportController::class, 'show'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::post('members/{member}/roles', [MemberController::class, 'assignRoles'])
            ->middleware(['throttle:api-write', 'permission:members.update,logto']);
        Route::delete('members/{member}/roles/{role}', [MemberController::class, 'removeRole'])
            ->middleware(['throttle:api-write', 'permission:members.update,logto']);

        // Roles & permissions
        Route::apiResource('roles', RoleController::class)
            ->middleware(['throttle:api', 'role:admin|superadmin,logto']);
        Route::get('permissions', [RoleController::class, 'permissions'])
            ->middleware(['throttle:api-read', 'role:admin|superadmin,logto']);
        Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
            ->middleware(['throttle:api-write', 'role:admin|superadmin,logto']);
        Route::post('roles/{role}/users', [RoleController::class, 'addUser'])
            ->middleware(['throttle:api-write', 'role:admin|superadmin,logto']);
        Route::delete('roles/{role}/users/{user}', [RoleController::class, 'removeUser'])
            ->middleware(['throttle:api-write', 'role:admin|superadmin,logto']);

        // Events — write operations
        Route::post('events', [EventController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:events.create,logto']);
        Route::put('events/{event}', [EventController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:events.update,logto']);
        Route::delete('events/{event}', [EventController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:events.delete,logto']);
        Route::post('events/{event}/cover', [EventController::class, 'uploadCover'])
            ->middleware(['throttle:api-write', 'permission:events.update,logto']);
        Route::delete('events/{event}/cover', [EventController::class, 'removeCover'])
            ->middleware(['throttle:api-write', 'permission:events.update,logto']);

        // Documents — write operations
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:documents.create,logto']);
        Route::put('documents/{document}', [DocumentController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:documents.update,logto']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:documents.delete,logto']);
        Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion'])
            ->middleware(['throttle:api-write', 'permission:documents.versions.create,logto']);

        // Document categories
        Route::apiResource('document-categories', DocumentCategoryController::class)
            ->middleware('throttle:api');

        // Financial records
        Route::get('financial-records/summary', [FinanceController::class, 'summary'])
            ->middleware(['throttle:api-read', 'permission:financials.view,logto']);
        Route::get('financial-records/export', [FinanceController::class, 'export'])
            ->middleware(['throttle:api-sensitive', 'permission:financials.export,logto']);
        Route::post('financial-records', [FinanceController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:financials.create,logto']);
        Route::put('financial-records/{record}', [FinanceController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:financials.update,logto']);
        Route::delete('financial-records/{record}', [FinanceController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:financials.delete,logto']);
        Route::get('financial-records/{record}', [FinanceController::class, 'show'])
            ->middleware(['throttle:api-read', 'permission:financials.view,logto']);
        Route::get('financial-records', [FinanceController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:financials.view,logto']);
        Route::apiResource('financial-categories', FinancialCategoryController::class)
            ->middleware('throttle:api');

        // Assets
        Route::get('assets/summary', [AssetController::class, 'summary'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
        Route::get('assets/categories', [AssetController::class, 'categories'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
        Route::get('assets/loans/overdue', [AssetLoanController::class, 'overdue'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
        Route::apiResource('assets', AssetController::class)
            ->middleware('throttle:api');
        Route::post('assets/{asset}/checkout', [AssetController::class, 'checkout'])
            ->middleware(['throttle:api-write', 'permission:assets.checkout,logto']);
        Route::post('assets/{asset}/return', [AssetController::class, 'return'])
            ->middleware(['throttle:api-write', 'permission:assets.return,logto']);
        Route::get('assets/{asset}/loans', [AssetLoanController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);

        // Payments — admin routes
        Route::get('payments', [PaymentController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:payments.view,logto']);
    });
});
