<?php

use App\Domain\FileHosting\Http\Controllers\Web\PublicShareController;
use Illuminate\Support\Facades\Route;

/*
| Guest share links (anyone with the link). No web.auth.
| UUID is the secret; private resources return 404.
*/
Route::get('s/f/{file}', [PublicShareController::class, 'showFile'])
    ->whereUuid('file')
    ->name('file-hosting.public.files.show');
// Temporary signed URL only — bare /s/f/{uuid}/download is rejected (403).
Route::get('s/f/{file}/download', [PublicShareController::class, 'downloadFile'])
    ->middleware('signed')
    ->whereUuid('file')
    ->name('file-hosting.public.files.download');
Route::get('s/d/{folder}', [PublicShareController::class, 'showFolder'])
    ->whereUuid('folder')
    ->name('file-hosting.public.folders.show');
