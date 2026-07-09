<?php

use App\Domain\Notification\Http\Controllers\Web\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
    ->whereUuid('notification')
    ->name('notifications.read');
