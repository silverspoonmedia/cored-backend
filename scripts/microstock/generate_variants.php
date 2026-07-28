<?php

/**
 * Generate persisted AI variants (ai_512, ai_1024) for every file in a folder.
 *
 * Usage: php scripts/microstock/generate_variants.php {folder_id}
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\FileHosting\Jobs\GenerateFileVariants;
use App\Domain\FileHosting\Models\HostedFile;

$folderId = $argv[1] ?? '019fa66d-65ae-72ea-bd20-8d0919ec5900';

$files = HostedFile::query()->where('folder_id', $folderId)->get();

foreach ($files as $file) {
    echo "Generating variants for {$file->name} ({$file->id})...\n";
    GenerateFileVariants::dispatchSync((string) $file->id);
}

echo "\nVariant status:\n";
foreach (HostedFile::query()->where('folder_id', $folderId)->with('variants')->orderBy('created_at')->get() as $file) {
    echo "{$file->name} ({$file->id})\n";
    foreach ($file->variants as $variant) {
        $kind = $variant->kind instanceof \BackedEnum ? $variant->kind->value : (string) $variant->kind;
        $status = $variant->status instanceof \BackedEnum ? $variant->status->value : (string) $variant->status;
        echo "  {$kind} {$status} {$variant->size} bytes\n";
    }
    if ($file->variants->isEmpty()) {
        echo "  (no variants)\n";
    }
}
