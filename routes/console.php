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

// vTual: channel fetch pool — age gate (15m) + size flush (50) via single locked flusher.
Schedule::command('vtual:flush-channel-pool')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command('vtual:flush-claim-verify-pool')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command('vtual:flush-channel-pool --resync')
    ->hourly()
    ->withoutOverlapping();

// vTual: content ingestion — feed discovery (zero quota) + content pool hydrate + live CCV poll.
Schedule::command('vtual:poll-channel-feeds')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
Schedule::command('vtual:flush-content-pool')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command('vtual:poll-live-contents')
    ->everyTwoMinutes()
    ->withoutOverlapping();
Schedule::command('vtual:renew-websub-subscriptions')
    ->daily()
    ->withoutOverlapping();
Schedule::command('vtual:repair-archived-websub')
    ->weekly()
    ->withoutOverlapping();
Schedule::command('vtual:renew-eventsub-subscriptions')
    ->daily()
    ->withoutOverlapping();
Schedule::command('vtual:prune-fetch-pools')
    ->daily()
    ->withoutOverlapping();

// vTual: AI classification audit pool. Rows the program classifier could not
// decide (music/non-music unknown, or music without a sub-category) are queued
// on hydrate; this drains them. One call measured 7-40 s, so the batch is small
// enough to finish inside the five-minute window — withoutOverlapping covers the
// rest. No-op while vtual.content_classification.ai.enabled is false.
Schedule::command('vtual:audit-content-ai --limit=5')
    ->everyFiveMinutes()
    ->withoutOverlapping();
