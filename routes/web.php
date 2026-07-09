<?php

use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\ApprovalController;
use App\Http\Controllers\Web\ApprovalWorkflowAdminController;
use App\Http\Controllers\Web\Auth\EmailVerificationController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\ProfileTwoFactorController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\TwoFactorController as TwoFactorWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\LookupController;
use App\Http\Controllers\Web\Navigation\MenuController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OrderAttachmentUploadController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web (Blade) frontend
|--------------------------------------------------------------------------
| The Blade frontend is a pure CONSUMER of the versioned API: every controller
| forwards to `/api/v1/*` (see App\Support\Api\ApiClient) and only renders the
| JSON it gets back. Business logic — validation, CRUD, RBAC — lives solely in
| the API, so these routes carry NO `permission:` gate on most paths: the API
| authorizes each forwarded call. Exception: `role:Root|Admin` on
| `admin/approval-workflows/*` (transport-layer gate before API forwarding).
| Web middleware here is transport-only: `web.guest`/`web.auth`
| (who is logged in, where to redirect) and the JWT-cookie bridge that lets the
| browser carry the same stateless token the API issues.
*/

Route::get('/', fn () => redirect()->route(Auth::guard('api')->check() ? 'dashboard' : 'login'));

Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Guest-only auth screens.
Route::middleware('web.guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('two-factor-challenge', [TwoFactorWebController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorWebController::class, 'store'])->name('two-factor.challenge.store');

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('forgot-password', [PasswordResetController::class, 'showLinkRequest'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');

    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

// Authenticated area.
Route::middleware('web.auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Approval management — forwarded to /api/v1/approvals (the API authorizes).
    Route::get('approvals/my-pending', [ApprovalController::class, 'myPending'])->name('approvals.my-pending');
    Route::get('approvals/instances/{instance}', [ApprovalController::class, 'show'])->whereUuid('instance')->name('approvals.show');
    Route::post('approvals/slots/{slot}/approve', [ApprovalController::class, 'approve'])->whereUuid('slot')->name('approvals.approve');
    Route::post('approvals/slots/{slot}/reject', [ApprovalController::class, 'reject'])->whereUuid('slot')->name('approvals.reject');
    Route::post('approvals/slots/{slot}/reassign', [ApprovalController::class, 'reassign'])->whereUuid('slot')->name('approvals.reassign');
    Route::post('approvals/slots/batch-approve', [ApprovalController::class, 'batchApprove'])->name('approvals.batch-approve');

    // Admin approval workflow setup.
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

    // Sidebar navigation tree (optional ?q= filter) — proxied to the API.
    Route::get('navigation/tree', [MenuController::class, 'navigationTree'])->name('navigation.tree');

    // Self-service profile.
    Route::get('profile/password', [PasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    Route::get('profile/two-factor', [ProfileTwoFactorController::class, 'edit'])->name('profile.two-factor.edit');
    Route::post('profile/two-factor/setup', [ProfileTwoFactorController::class, 'setup'])->name('profile.two-factor.setup');
    Route::post('profile/two-factor/confirm', [ProfileTwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
    Route::post('profile/two-factor/disable', [ProfileTwoFactorController::class, 'disable'])->name('profile.two-factor.disable');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');

    // Lookup feeds for Select2 AJAX fields.
    Route::prefix('lookups')->name('lookups.')->group(function (): void {
        Route::get('users', [LookupController::class, 'users'])->name('users');
        Route::get('menus', [LookupController::class, 'menus'])->name('menus');
        Route::get('roles', [LookupController::class, 'roles'])->name('roles');
    });

    // User management — forwarded to /api/v1/users (the API authorizes).
    Route::get('users/datatable', [UserController::class, 'datatable'])->name('users.datatable');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->whereUuid('user')->name('users.edit');
    Route::apiResource('users', UserController::class)->except(['show'])->whereUuid('user');

    // Master-detail orders (playground reference) — forwarded to /api/playground/orders.
    Route::get('orders/datatable', [OrderController::class, 'datatable'])->name('orders.datatable');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->whereUuid('order')->name('orders.edit');
    Route::prefix('orders/attachments/uploads')->name('orders.attachments.uploads.')->group(function () {
        Route::post('/', [OrderAttachmentUploadController::class, 'store'])->name('store');
        Route::get('{upload}', [OrderAttachmentUploadController::class, 'show'])->name('show')->whereUuid('upload');
        Route::post('{upload}/parts', [OrderAttachmentUploadController::class, 'signParts'])->name('sign-parts')->whereUuid('upload');
        Route::post('{upload}/complete', [OrderAttachmentUploadController::class, 'complete'])->name('complete')->whereUuid('upload');
        Route::delete('{upload}', [OrderAttachmentUploadController::class, 'destroy'])->name('destroy')->whereUuid('upload');
    });
    // Mass-delete (literal `bulk` segment declared before the resource so it is
    // not captured by the `{order}` wildcard). Selected ids ride in the body.
    Route::delete('orders/bulk', [OrderController::class, 'bulkDestroy'])->name('orders.bulk-destroy');
    Route::apiResource('orders', OrderController::class)->except(['create', 'edit'])->whereUuid('order');

    // Navigation menu management — forwarded to /api/v1/navigation/menus.
    Route::get('menus/trashed', [MenuController::class, 'trashed'])->name('menus.trashed');
    Route::get('menus/create', [MenuController::class, 'create'])->name('menus.create');
    Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->whereUuid('menu')->name('menus.edit');
    Route::post('menus/{menu}/restore', [MenuController::class, 'restore'])->whereUuid('menu')->name('menus.restore');
    Route::apiResource('menus', MenuController::class)->except(['show'])->whereUuid('menu');

    // Activity audit log — forwarded to /api/v1/activity-logs (the API authorizes).
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('activity-logs/{activity}', [ActivityLogController::class, 'show'])
        ->whereNumber('activity')
        ->name('activity-logs.show');

    // Activity notifications — forwarded to /api/v1/notifications (the API authorizes).
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->whereUuid('notification')
        ->name('notifications.read');
});
