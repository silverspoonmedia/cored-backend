<?php

use App\Domain\ErrorLog\Http\Controllers\Web\ErrorLogController;
use Illuminate\Support\Facades\Route;

Route::get('error-logs', [ErrorLogController::class, 'index'])->name('error-logs.index');
Route::get('error-logs/{errorLog}', [ErrorLogController::class, 'show'])
    ->whereUuid('errorLog')
    ->name('error-logs.show');
Route::patch('error-logs/{errorLog}/status', [ErrorLogController::class, 'updateStatus'])
    ->whereUuid('errorLog')
    ->name('error-logs.status');
