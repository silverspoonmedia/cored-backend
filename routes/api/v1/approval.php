<?php

use App\Domain\Approval\Http\Controllers\API\ApprovalController;
use App\Domain\Approval\Http\Controllers\API\ApprovalWorkflowAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('approvals')->name('approvals.')->group(function (): void {
    Route::get('my-pending', [ApprovalController::class, 'myPending'])->name('my-pending');
    Route::get('instances/{instance}', [ApprovalController::class, 'show'])->name('show');
    Route::post('slots/{slot}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('slots/{slot}/reject', [ApprovalController::class, 'reject'])->name('reject');
    Route::post('slots/{slot}/reassign', [ApprovalController::class, 'reassign'])->name('reassign');
    Route::post('slots/batch-approve', [ApprovalController::class, 'batchApprove'])->name('batch-approve');
});

Route::middleware('auth:api')->prefix('admin/approval-workflows')->name('admin.approval-workflows.')->group(function (): void {
    Route::get('/', [ApprovalWorkflowAdminController::class, 'index'])->name('index');
    Route::post('/', [ApprovalWorkflowAdminController::class, 'store'])->name('store');
    Route::get('{workflow}', [ApprovalWorkflowAdminController::class, 'show'])->whereUuid('workflow')->name('show');
    Route::put('{workflow}', [ApprovalWorkflowAdminController::class, 'update'])->whereUuid('workflow')->name('update');
    Route::post('{workflow}/activate', [ApprovalWorkflowAdminController::class, 'activate'])->whereUuid('workflow')->name('activate');
    Route::post('{workflow}/deactivate', [ApprovalWorkflowAdminController::class, 'deactivate'])->whereUuid('workflow')->name('deactivate');
    Route::post('{workflow}/steps', [ApprovalWorkflowAdminController::class, 'storeStep'])->whereUuid('workflow')->name('steps.store');
    Route::delete('{workflow}/steps/{step}', [ApprovalWorkflowAdminController::class, 'destroyStep'])->whereUuid('workflow')->whereUuid('step')->name('steps.destroy');
    Route::post('{workflow}/steps/{step}/approvers', [ApprovalWorkflowAdminController::class, 'storeApprover'])->whereUuid('workflow')->whereUuid('step')->name('steps.approvers.store');
    Route::delete('{workflow}/steps/{step}/approvers/{approver}', [ApprovalWorkflowAdminController::class, 'destroyApprover'])->whereUuid('workflow')->whereUuid('step')->whereUuid('approver')->name('steps.approvers.destroy');
});
