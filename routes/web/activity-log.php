<?php

use App\Domain\ActivityLog\Http\Controllers\Web\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
Route::get('activity-logs/{activity}', [ActivityLogController::class, 'show'])
    ->whereNumber('activity')
    ->name('activity-logs.show');
