<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Support\Collection;

/**
 * CSV export of finalized microstock metadata. Generic columns (config-driven);
 * per-marketplace field mapping is a later iteration.
 */
class CsvExportService
{
    /**
     * Build CSV lines for a user's finalized metadata within their Microstock
     * system workspace.
     *
     * @return array{rows: list<list<string>>, count: int}
     */
    public function build(User $owner): array
    {
        $workspace = app(MicrostockService::class)->workspaceFor($owner);

        /** @var Collection<int, AssetMetadata> $rows */
        $rows = AssetMetadata::query()
            ->where('user_id', $owner->id)
            ->where('status', MetadataStatus::Finalized)
            ->whereHas('file', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with('file:id,name')
            ->orderBy('created_at')
            ->get();

        $columns = config('microstock.csv.columns', ['filename', 'title', 'keywords']);
        $separator = (string) config('microstock.csv.keywords_separator', ',');

        $out = [$columns];

        foreach ($rows as $row) {
            $out[] = $this->line($row, $columns, $separator);
        }

        return ['rows' => $out, 'count' => $rows->count()];
    }

    /**
     * Build CSV lines for the finalized metadata of files directly inside one
     * collection folder (non-recursive).
     *
     * @return array{rows: list<list<string>>, count: int}
     */
    public function buildForCollection(Folder $folder): array
    {
        /** @var Collection<int, AssetMetadata> $rows */
        $rows = AssetMetadata::query()
            ->where('status', MetadataStatus::Finalized)
            ->whereHas('file', fn ($q) => $q->where('folder_id', $folder->id))
            ->with('file:id,name')
            ->orderBy('created_at')
            ->get();

        $columns = config('microstock.csv.columns', ['filename', 'title', 'keywords']);
        $separator = (string) config('microstock.csv.keywords_separator', ',');

        $out = [$columns];

        foreach ($rows as $row) {
            $out[] = $this->line($row, $columns, $separator);
        }

        return ['rows' => $out, 'count' => $rows->count()];
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function line(AssetMetadata $row, array $columns, string $separator): array
    {
        return array_map(static function (string $col) use ($row, $separator): string {
            return match ($col) {
                'filename' => (string) ($row->file->name ?? ''),
                'title' => (string) ($row->title ?? ''),
                'description' => (string) ($row->description ?? ''),
                'category' => (string) ($row->category ?? ''),
                'keywords' => implode($separator.' ', $row->keywords ?? []),
                default => '',
            };
        }, $columns);
    }
}
