<?php

use App\Http\Controllers\API\ApprovalController;
use App\Http\Controllers\API\ApprovalWorkflowAdminController;
use App\Http\Controllers\API\PlaygroundController;
use App\Http\Controllers\API\PlaygroundOrderController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

// Approval routes (authenticated)
Route::middleware('auth:api')->prefix('v1/approvals')->group(function () {
    Route::get('my-pending', [ApprovalController::class, 'myPending']);
    Route::get('instances/{instance}', [ApprovalController::class, 'show']);
    Route::post('slots/{slot}/approve', [ApprovalController::class, 'approve']);
    Route::post('slots/{slot}/reject', [ApprovalController::class, 'reject']);
    Route::post('slots/{slot}/reassign', [ApprovalController::class, 'reassign']);
    Route::post('slots/batch-approve', [ApprovalController::class, 'batchApprove']);
});

// Approval workflow admin (Root/Admin access enforced within controllers).
Route::middleware('auth:api')->prefix('v1/admin/approval-workflows')->group(function () {
    Route::get('/', [ApprovalWorkflowAdminController::class, 'index']);
    Route::post('/', [ApprovalWorkflowAdminController::class, 'store']);
    Route::get('{workflow}', [ApprovalWorkflowAdminController::class, 'show'])->whereUuid('workflow');
    Route::put('{workflow}', [ApprovalWorkflowAdminController::class, 'update'])->whereUuid('workflow');
    Route::post('{workflow}/activate', [ApprovalWorkflowAdminController::class, 'activate'])->whereUuid('workflow');
    Route::post('{workflow}/deactivate', [ApprovalWorkflowAdminController::class, 'deactivate'])->whereUuid('workflow');
    Route::post('{workflow}/steps', [ApprovalWorkflowAdminController::class, 'storeStep'])->whereUuid('workflow');
    Route::delete('{workflow}/steps/{step}', [ApprovalWorkflowAdminController::class, 'destroyStep'])->whereUuid('workflow')->whereUuid('step');
    Route::post('{workflow}/steps/{step}/approvers', [ApprovalWorkflowAdminController::class, 'storeApprover'])->whereUuid('workflow')->whereUuid('step');
    Route::delete('{workflow}/steps/{step}/approvers/{approver}', [ApprovalWorkflowAdminController::class, 'destroyApprover'])->whereUuid('workflow')->whereUuid('step')->whereUuid('approver');
});

// Playground routes: demo CRUD surface for testing. Disabled in production for
// security (no authentication/authorization). See SECURITY_AUDIT.md FINDING-001.
// if (! app()->environment('production')) {
Route::prefix('playground')->group(function () {
    // Namespaced route names (`playground.users.*`) so the demo surface does not
    // squat the generic `users.*` names used by the web admin UI.
    Route::apiResource('users', PlaygroundController::class)
        ->except(['create', 'edit'])
        ->names('playground.users');

    // Mass-delete (declared before the resource so the literal segment is not
    // captured by the `{order}` wildcard). Ids ride in the request body.
    Route::delete('orders/bulk', [PlaygroundOrderController::class, 'bulkDestroy'])
        ->name('playground.orders.bulk-destroy');

    // Master-detail reference CRUD (Order + OrderDetail). Standard five-action
    // resource; the atomic two-table write lives in OrderService/OrderRepository.
    Route::apiResource('orders', PlaygroundOrderController::class)
        ->except(['create', 'edit'])
        ->names('playground.orders');
});
// }
