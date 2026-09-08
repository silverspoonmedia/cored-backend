<?php

namespace App\Domain\Archivd\Jobs;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\FileVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFileVariants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        private readonly string $fileId,
    ) {}

    public function handle(FileVariantService $variants): void
    {
        $file = HostedFile::query()->find($this->fileId);

        if ($file === null) {
            return;
        }

        $variants->generateForFile($file);
    }
}
