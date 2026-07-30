<?php

/**
 * Live env QA runner — logs steps without printing secret values.
 * Usage: php storage/framework/env-qa-run.php
 */

$root = dirname(__DIR__, 2);
$logPath = $root.'/storage/logs/env-qa-'.date('Y-m-d-His').'.md';

function logStep(string $logPath, string $step, string $output, string $envPath): void
{
    $hash = is_file($envPath) ? hash_file('sha256', $envPath) : 'missing';
    $lines = is_file($envPath) ? count(file($envPath)) : 0;
    $entry = "## {$step}\n\n";
    $entry .= '- Time: '.date('c')."\n";
    $entry .= "- `.env` sha256: `{$hash}`\n";
    $entry .= "- `.env` lines: {$lines}\n\n";
    $entry .= "```\n".trim($output)."\n```\n\n";
    file_put_contents($logPath, $entry, FILE_APPEND);
}

$envPath = $root.'/.env';
file_put_contents($logPath, "# Env QA live run\n\nRoot: `{$root}`\n\n");

function run(string $cmd, string $root): string
{
    chdir($root);
    ob_start();
    passthru($cmd.' 2>&1', $code);
    $out = ob_get_clean();

    return trim($out)."\n[exit={$code}]";
}

logStep($logPath, 'L00 baseline', run('php artisan env:switch', $root), $envPath);
logStep($logPath, 'L01 curate dry-run', run('php artisan env:curate --dry-run --no-interaction', $root), $envPath);
logStep($logPath, 'L02 curate apply + sync-snapshot', run('php artisan env:curate --sync-snapshot --no-interaction', $root), $envPath);
logStep($logPath, 'L03 switch staging', run('php artisan env:switch staging --no-interaction', $root), $envPath);
logStep($logPath, 'L04 switch local', run('php artisan env:switch local --no-interaction', $root), $envPath);
logStep($logPath, 'L05 restore list', run('php artisan env:restore --list', $root), $envPath);

// Verify critical keys still present (names only, not values)
$contents = file_get_contents($envPath);
$checks = ['APP_KEY=', 'JWT_SECRET=', 'DB_DATABASE=', 'FILE_HOSTING_S3_CE_BUCKET=', 'MIDTRANS_SERVER_KEY='];
$checkLines = [];
foreach ($checks as $needle) {
    $checkLines[] = $needle.' '.(str_contains($contents, $needle) ? 'OK' : 'MISSING');
}
logStep($logPath, 'L06 key presence check', implode("\n", $checkLines), $envPath);

echo "Log written to: {$logPath}\n";
