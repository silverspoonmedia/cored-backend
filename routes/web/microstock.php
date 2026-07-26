<?php

use App\Domain\Microstock\Http\Controllers\Web\MicrostockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Microstock Web routes
|--------------------------------------------------------------------------
| Thin shell over the File Hosting browse SPA pinned to the owner's
| Microstock system workspace, plus the metadata CSV export download.
*/

Route::get('microstock', [MicrostockController::class, 'index'])
    ->name('microstock.index');

Route::get('microstock/export/csv', [MicrostockController::class, 'exportCsv'])
    ->name('microstock.export.csv');
