<?php

namespace App\Domain\Archivd\Http\Resources;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\HashBlocklistService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class HostedFileResourceCollection extends ResourceCollection
{
    public $collects = HostedFileResource::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        app(HashBlocklistService::class)->primeRequestLookup(
            $request,
            $this->collection->map(fn (mixed $item): ?string => $this->checksumOf($item)),
        );

        return parent::toArray($request);
    }

    private function checksumOf(mixed $item): ?string
    {
        if ($item instanceof HostedFileResource) {
            $item = $item->resource;
        }

        if (! $item instanceof HostedFile) {
            return null;
        }

        return is_string($item->checksum) ? $item->checksum : null;
    }
}
