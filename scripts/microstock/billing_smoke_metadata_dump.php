<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fid = $argv[1] ?? '019fbc21-eff1-72af-b14c-494ecbe14328';
$files = \App\Domain\FileHosting\Models\HostedFile::where('folder_id', $fid)->orderBy('name')->get();
foreach ($files as $file) {
    $m = \App\Domain\Microstock\Models\AssetMetadata::where('file_id', $file->id)->first();
    echo "=== {$file->name} ===\n";
    if (!$m) { echo "no metadata\n"; continue; }
    echo "status={$m->status?->value} billing={$m->billing_source} run={$m->analysis_run_id}\n";
    echo "error={$m->error}\n";
    echo "ai_vision=".json_encode($m->ai_vision)."\n";
    echo "ai_thinking=".json_encode($m->ai_thinking)."\n";
    echo "ai_billing=".json_encode($m->ai_billing)."\n";
}
