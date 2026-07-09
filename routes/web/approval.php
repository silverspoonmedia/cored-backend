<?php

use App\Domain\Approval\Http\Controllers\Web\ApprovalController;
use App\Domain\Approval\Http\Controllers\Web\ApprovalWorkflowAdminController;
use Illuminate\Support\Facades\Route;

Route::get('approvals/my-pending', [ApprovalController::class, 'myPending'])->name('approvals.my-pending');
Route::get('approvals/instances/{instance}', [ApprovalController::class, 'show'])->whereUuid('instance')->name('approvals.show');
Route::post('approvals/slots/{slot}/approve', [ApprovalController::class, 'approve'])->whereUuid('slot')->name('approvals.approve');
Route::post('approvals/slots/{slot}/reject', [ApprovalController::class, 'reject'])->whereUuid('slot')->name('approvals.reject');
Route::post('approvals/slots/{slot}/reassign', [ApprovalController::class, 'reassign'])->whereUuid('slot')->name('approvals.reassign');
Route::post('approvals/slots/batch-approve', [ApprovalController::class, 'batchApprove'])->name('approvals.batch-approve');

Route::middleware('role:Root|Admin')->prefix('admin/approval-workflows')->group(function (): void {
    Route::get('/', [ApprovalWorkflowAdminController::class, 'index'])->name('admin.approval-workflows.index');
    Route::get('/create', [ApprovalWorkflowAdminController::class, 'create'])->name('admin.approval-workflows.create');
    Route::post('/', [ApprovalWorkflowAdminController::class, 'store'])->name('admin.approval-workflows.store');
    Route::get('/{workflow}', [ApprovalWorkflowAdminController::class, 'show'])->name('admin.approval-workflows.show')->whereUuid('workflow');
    Route::get('/{workflow}/edit', [ApprovalWorkflowAdminController::class, 'edit'])->name('admin.approval-workflows.edit')->whereUuid('workflow');
    Route::put('/{workflow}', [ApprovalWorkflowAdminController::class, 'update'])->name('admin.approval-workflows.update')->whereUuid('workflow');
    Route::post('/{workflow}/activate', [ApprovalWorkflowAdminController::class, 'activate'])->name('admin.approval-workflows.activate')->whereUuid('workflow');
    Route::post('/{workflow}/deactivate', [ApprovalWorkflowAdminController::class, 'deactivate'])->name('admin.approval-workflows.deactivate')->whereUuid('workflow');
    Route::post('/{workflow}/steps', [ApprovalWorkflowAdminController::class, 'storeStep'])->name('admin.approval-workflows.steps.store')->whereUuid('workflow');
    Route::delete('/{workflow}/steps/{step}', [ApprovalWorkflowAdminController::class, 'destroyStep'])->name('admin.approval-workflows.steps.destroy')->whereUuid(['workflow', 'step']);
    Route::post('/{workflow}/steps/{step}/approvers', [ApprovalWorkflowAdminController::class, 'storeApprover'])->name('admin.approval-workflows.steps.approvers.store')->whereUuid(['workflow', 'step']);
    Route::delete('/{workflow}/steps/{step}/approvers/{approver}', [ApprovalWorkflowAdminController::class, 'destroyApprover'])->name('admin.approval-workflows.steps.approvers.destroy')->whereUuid(['workflow', 'step', 'approver']);
});
