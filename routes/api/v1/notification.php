<?php

use App\Domain\Notification\Http\Controllers\API\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view notification'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])
            ->whereUuid('notification')
            ->name('read');
    });
