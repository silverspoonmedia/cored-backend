<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;

$paidId = '019fbc22-1585-72f4-86d5-ac33faf984d5';
$f = Folder::query()->find($paidId);
echo 'paid status: '.($f->collection_status?->value ?? 'null')."\n";

// Zombie processing blocks sync watchdog loop during free probe — mark failed so paid --force can re-run.
if ($f && $f->collection_status === CollectionStatus::Processing) {
    $metaCount = \App\Domain\Microstock\Models\AssetMetadata::query()
        ->whereIn('file_id', \App\Domain\FileHosting\Models\HostedFile::where('folder_id', $paidId)->pluck('id'))
        ->count();
    if ($metaCount === 0) {
        $f->markCollectionStatus(CollectionStatus::Failed);
        echo "marked paid folder failed (zombie processing, no metadata)\n";
    }
}
