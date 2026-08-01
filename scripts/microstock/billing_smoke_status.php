<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fid = '019fbc21-eff1-72af-b14c-494ecbe14328';
$f = \App\Domain\FileHosting\Models\Folder::find($fid);
echo 'status: '.($f->collection_status?->value ?? 'null')."\n";
$files = \App\Domain\FileHosting\Models\HostedFile::where('folder_id', $fid)->pluck('id');
$meta = \App\Domain\Microstock\Models\AssetMetadata::whereIn('file_id', $files)->get();
echo 'metadata rows: '.$meta->count()."\n";
foreach ($meta as $m) {
    $v = is_array($m->ai_vision) ? ($m->ai_vision['status'] ?? '?') : '?';
    $t = is_array($m->ai_thinking) ? ($m->ai_thinking['status'] ?? '?') : '?';
    $r = is_array($m->ai_result) ? ($m->ai_result['status'] ?? '?') : '?';
    echo "{$m->file_id}: status={$m->status?->value} billing={$m->billing_source} v=$v t=$t r=$r\n";
}
