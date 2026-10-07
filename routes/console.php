<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:midtrans-reconcile-pending')->everyFiveMinutes();
Schedule::command('payments:reconcile-withdrawable-funds')->everyTenMinutes();
Schedule::command('service:process-entitlements')->hourly();
Schedule::command('service:process-addon-renewals')->hourly();

// Archivd: reclaim orphaned multipart storage from abandoned upload sessions
// (Backblaze has no S3 lifecycle for incomplete multiparts).
Schedule::command('archivd:prune-stale-uploads')
    ->hourly()
    ->withoutOverlapping();
