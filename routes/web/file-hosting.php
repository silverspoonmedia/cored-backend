<?php

use App\Domain\FileHosting\Http\Controllers\Web\FileManagerController;
use Illuminate\Support\Facades\Route;

Route::middleware('domain.access:FileHosting')->group(function (): void {
    Route::get('file-hosting', [FileManagerController::class, 'index'])->name('file-hosting.index');
    Route::get('file-hosting/trash', [FileManagerController::class, 'trash'])->name('file-hosting.trash');
    Route::get('file-hosting/quota', [FileManagerController::class, 'quota'])->name('file-hosting.quota');

    // Collaborative workspaces (Blade consumer of API)
    Route::get('file-hosting/workspaces', [FileManagerController::class, 'workspaces'])
        ->name('file-hosting.workspaces.index');
    Route::post('file-hosting/workspaces', [FileManagerController::class, 'storeWorkspace'])->name('file-hosting.workspaces.store');
    Route::post('file-hosting/workspaces/invites/accept', [FileManagerController::class, 'acceptWorkspaceInvite'])
        ->name('file-hosting.workspaces.invites.accept');
    Route::get('file-hosting/workspaces/{workspace}/members', [FileManagerController::class, 'workspaceMembers'])
        ->whereUuid('workspace')
        ->name('file-hosting.workspaces.members.index');
    Route::get('file-hosting/workspaces/{workspace}/activity', [FileManagerController::class, 'workspaceActivity'])
        ->whereUuid('workspace')
        ->name('file-hosting.workspaces.activity');
    Route::post('file-hosting/workspaces/{workspace}/invites', [FileManagerController::class, 'inviteWorkspaceMember'])
        ->whereUuid('workspace')
        ->name('file-hosting.workspaces.invites.store');
    Route::delete('file-hosting/workspaces/{workspace}/members/{user}', [FileManagerController::class, 'removeWorkspaceMember'])
        ->whereUuid(['workspace', 'user'])
        ->name('file-hosting.workspaces.members.destroy');
    Route::post('file-hosting/workspaces/{workspace}/archive', [FileManagerController::class, 'archiveWorkspace'])
        ->whereUuid('workspace')
        ->name('file-hosting.workspaces.archive');

    // Microstock standalone workspace slots (Blade consumer of API)
    Route::get('file-hosting/microstock/slots', [FileManagerController::class, 'microstockSlots'])
        ->name('file-hosting.microstock.slots');
    Route::post('file-hosting/microstock/workspaces', [FileManagerController::class, 'storeMicrostockWorkspace'])
        ->name('file-hosting.microstock.workspaces.store');

    Route::post('file-hosting/folders', [FileManagerController::class, 'storeFolder'])->name('file-hosting.folders.store');
    Route::match(['put', 'patch'], 'file-hosting/folders/{folder}/rename', [FileManagerController::class, 'renameFolder'])
        ->whereUuid('folder')
        ->name('file-hosting.folders.rename');
    Route::match(['put', 'patch'], 'file-hosting/folders/{folder}/move', [FileManagerController::class, 'moveFolder'])
        ->whereUuid('folder')
        ->name('file-hosting.folders.move');
    Route::match(['put', 'patch'], 'file-hosting/folders/{folder}/visibility', [FileManagerController::class, 'setFolderVisibility'])
        ->whereUuid('folder')
        ->name('file-hosting.folders.visibility');
    Route::post('file-hosting/folders/{folder}/restore', [FileManagerController::class, 'restoreFolder'])
        ->whereUuid('folder')
        ->name('file-hosting.folders.restore');
    Route::delete('file-hosting/folders/{folder}', [FileManagerController::class, 'destroyFolder'])
        ->whereUuid('folder')
        ->name('file-hosting.folders.destroy');
    Route::delete('file-hosting/folders/{folder}/force', [FileManagerController::class, 'forceDestroyFolder'])
        ->whereUuid('folder')
        ->name('file-hosting.folders.force-destroy');
    Route::get('file-hosting/files/{file}', [FileManagerController::class, 'show'])
        ->whereUuid('file')
        ->name('file-hosting.files.show');
    Route::get('file-hosting/files/{file}/download', [FileManagerController::class, 'download'])
        ->whereUuid('file')
        ->name('file-hosting.files.download');
    Route::match(['put', 'patch'], 'file-hosting/files/{file}/rename', [FileManagerController::class, 'renameFile'])
        ->whereUuid('file')
        ->name('file-hosting.files.rename');
    Route::match(['put', 'patch'], 'file-hosting/files/{file}/move', [FileManagerController::class, 'moveFile'])
        ->whereUuid('file')
        ->name('file-hosting.files.move');
    Route::match(['put', 'patch'], 'file-hosting/files/{file}/visibility', [FileManagerController::class, 'setFileVisibility'])
        ->whereUuid('file')
        ->name('file-hosting.files.visibility');
    Route::post('file-hosting/files/{file}/restore', [FileManagerController::class, 'restoreFile'])
        ->whereUuid('file')
        ->name('file-hosting.files.restore');
    Route::delete('file-hosting/files/{file}', [FileManagerController::class, 'destroyFile'])
        ->whereUuid('file')
        ->name('file-hosting.files.destroy');
    Route::delete('file-hosting/files/{file}/force', [FileManagerController::class, 'forceDestroyFile'])
        ->whereUuid('file')
        ->name('file-hosting.files.force-destroy');

    Route::get('file-hosting/search', [FileManagerController::class, 'search'])
        ->name('file-hosting.search');

    Route::post('file-hosting/files/bulk-visibility', [FileManagerController::class, 'bulkFileVisibility'])
        ->name('file-hosting.files.bulk-visibility');
    Route::delete('file-hosting/files/bulk', [FileManagerController::class, 'bulkFileDestroy'])
        ->name('file-hosting.files.bulk-destroy');
    Route::post('file-hosting/folders/bulk-visibility', [FileManagerController::class, 'bulkFolderVisibility'])
        ->name('file-hosting.folders.bulk-visibility');
    Route::delete('file-hosting/folders/bulk', [FileManagerController::class, 'bulkFolderDestroy'])
        ->name('file-hosting.folders.bulk-destroy');

    Route::post('file-hosting/uploads', [FileManagerController::class, 'initUpload'])->name('file-hosting.uploads.store');
    Route::get('file-hosting/uploads/{upload}', [FileManagerController::class, 'uploadStatus'])
        ->whereUuid('upload')
        ->name('file-hosting.uploads.show');
    Route::post('file-hosting/uploads/{upload}/sign-parts', [FileManagerController::class, 'signParts'])
        ->whereUuid('upload')
        ->name('file-hosting.uploads.sign-parts');
    Route::post('file-hosting/uploads/{upload}/complete', [FileManagerController::class, 'completeUpload'])
        ->whereUuid('upload')
        ->name('file-hosting.uploads.complete');
    Route::delete('file-hosting/uploads/{upload}', [FileManagerController::class, 'abortUpload'])
        ->whereUuid('upload')
        ->name('file-hosting.uploads.destroy');

    // DMCA hash blocklist (platform admin)
    Route::middleware('permission:manage any file hosting resource')->group(function (): void {
        Route::get('file-hosting/blocklist', [FileManagerController::class, 'blocklist'])->name('file-hosting.blocklist.index');
        Route::post('file-hosting/blocklist', [FileManagerController::class, 'storeBlocklistEntry'])->name('file-hosting.blocklist.store');
        Route::delete('file-hosting/blocklist/{entry}', [FileManagerController::class, 'destroyBlocklistEntry'])
            ->whereUuid('entry')
            ->name('file-hosting.blocklist.destroy');
    });
});
