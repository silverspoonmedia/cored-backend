<?php

use App\Domain\Notification\Http\Controllers\Web\NotificationAudienceController;
use App\Domain\Notification\Http\Controllers\Web\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view settings')
    ->prefix('notification-audiences')
    ->name('notification-audiences.')
    ->group(function (): void {
        Route::get('/', [NotificationAudienceController::class, 'index'])->name('index');
        Route::get('{type}/edit', [NotificationAudienceController::class, 'edit'])->name('edit');

        Route::middleware('permission:manage settings')->group(function (): void {
            Route::put('{type}', [NotificationAudienceController::class, 'update'])->name('update');
        });
    });

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
    ->whereUuid('notification')
    ->name('notifications.read');
