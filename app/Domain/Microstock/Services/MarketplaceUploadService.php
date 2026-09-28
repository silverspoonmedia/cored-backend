<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MarketplaceUploadStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\UploadCollectionToMarketplace;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Models\MarketplaceCredential;
use App\Domain\Microstock\Models\MarketplaceUpload;
use App\Domain\Microstock\Support\MicrostockRealtime;
use Illuminate\Database\Eloquent\Collection;

/**
 * Marketplace upload (decision:
 * docs/domains/microstock/reference-marketplace-upload.md).
 *
 * An upload is a **copy out**: source files, derivatives, metadata rows and the
 * exported CSV all stay where they are. What this service owns is the plan (which
 * files, which metadata rows, which remote path) and the attempt row; the
 * transfer itself belongs to `UploadCollectionToMarketplace` + a
 * `MarketplaceTransport`.
 */
class MarketplaceUploadService
{
    /**
     * Filenames a marketplace can match reliably. A name with a space, a quote
     * or a non-ASCII character round-trips differently through the marketplace's
     * importer than it does through SFTP, and a mismatched name silently detaches
     * the metadata row from its file.
     */
    private const SAFE_FILENAME = '/^[A-Za-z0-9._-]+$/';

    public function __construct(
        private readonly CollectionService $collections,
        private readonly MicrostockRealtime $realtime,
    ) {}

    /**
     * Resolve a configured marketplace profile, or fail closed.
     *
     * @return array<string, mixed>
     */
    public function profile(string $marketplace): array
    {
        $profile = config('microstock.upload.marketplaces.'.$marketplace);

        if (! is_array($profile) || $profile === []) {
            throw new MicrostockException(
                __('api/microstock.upload_marketplace_unsupported', ['marketplace' => $marketplace]),
                422,
            );
        }

        return $profile;
    }

    /**
     * Queue an upload for one collection using one of the actor's credentials.
     */
    public function queue(User $actor, string $folderId, string $credentialId): MarketplaceUpload
    {
        if (! (bool) config('microstock.upload.enabled', true)) {
            throw new MicrostockException(__('api/microstock.upload_disabled'), 422);
        }

        $folder = $this->collections->resolveCollection($actor, $folderId);

        $status = $folder->collection_status ?? CollectionStatus::Draft;
        if (! in_array($status, [CollectionStatus::Review, CollectionStatus::Exported], true)) {
            throw new MicrostockException(__('api/microstock.upload_not_ready'), 422);
        }

        $credential = $this->resolveCredential($actor, $credentialId);
        $profile = $this->profile($credential->marketplace);

        $plan = $this->plan($folder, $credential, $profile);

        if ($plan['files'] === []) {
            throw new MicrostockException(__('api/microstock.upload_nothing_finalized'), 422);
        }

        $upload = MarketplaceUpload::query()->create([
            'user_id' => $actor->id,
            'folder_id' => $folder->id,
            'credential_id' => $credential->id,
            'marketplace' => $credential->marketplace,
            'status' => MarketplaceUploadStatus::Queued,
            'remote_path' => $plan['remote_path'],
            'file_count' => count($plan['files']),
            'byte_count' => $plan['bytes'],
        ]);

        UploadCollectionToMarketplace::dispatch((string) $upload->id)
            ->onQueue((string) config('microstock.queues.upload', 'microstock-upload'));

        $this->realtime->collectionProgress($folder, force: true);

        return $upload;
    }

    /**
     * Latest attempt for a collection (the SPA status column reads this).
     */
    public function latestFor(User $actor, string $folderId): ?MarketplaceUpload
    {
        $folder = $this->collections->resolveCollection($actor, $folderId);

        return MarketplaceUpload::query()
            ->where('folder_id', $folder->id)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Build the batch: finalized rows, validated filenames, manifest contents.
     *
     * @param  array<string, mixed>  $profile
     * @return array{files: list<array{name: string, disk: string, path: string, size: int}>, manifest_name: string, manifest_contents: string, remote_path: string, bytes: int}
     */
    public function plan(Folder $folder, MarketplaceCredential $credential, array $profile): array
    {
        $manifestName = (string) ($profile['manifest_name'] ?? 'metadata.csv');
        $remotePath = $this->remotePath($credential, $profile);
        $rows = $this->finalizedRows($folder);

        if ($rows->isEmpty()) {
            return [
                'files' => [],
                'manifest_name' => $manifestName,
                'manifest_contents' => '',
                'remote_path' => $remotePath,
                'bytes' => 0,
            ];
        }

        $files = [];
        $seen = [];
        $lines = [$this->manifestHeader($profile)];

        foreach ($rows as $row) {
            $file = $row->file;

            if ($file === null) {
                continue;
            }

            $name = (string) $file->name;

            if (! $this->isSafeFilename($name)) {
                throw new MicrostockException(
                    __('api/microstock.upload_filename_invalid', ['name' => $name]),
                    422,
                );
            }

            if (isset($seen[$name])) {
                throw new MicrostockException(
                    __('api/microstock.upload_filename_duplicate', ['name' => $name]),
                    422,
                );
            }

            $seen[$name] = true;

            $lines[] = $this->manifestLine($profile, $row, $name);

            $files[] = [
                'name' => $name,
                'disk' => (string) $file->disk,
                'path' => (string) $file->path,
                'size' => (int) ($file->size ?? 0),
            ];
        }

        return [
            'files' => $files,
            'manifest_name' => $manifestName,
            'manifest_contents' => $this->renderCsv($lines),
            'remote_path' => $remotePath,
            'bytes' => (int) array_sum(array_column($files, 'size')),
        ];
    }

    /**
     * Credential owned by the actor. A shared workspace does not share
     * credentials, so a foreign id is a 404 — the same shape as delete.
     */
    public function resolveCredential(User $actor, string $credentialId): MarketplaceCredential
    {
        $credential = MarketplaceCredential::query()
            ->where('user_id', $actor->id)
            ->whereKey($credentialId)
            ->first();

        if ($credential === null) {
            throw new MicrostockException(__('api/microstock.credential_not_found'), 404);
        }

        return $credential;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function remotePath(MarketplaceCredential $credential, array $profile): string
    {
        $secret = $credential->secret_payload;
        $fromCredential = is_array($secret) ? trim((string) ($secret['remote_path'] ?? '')) : '';

        return $fromCredential !== ''
            ? $fromCredential
            : (string) ($profile['remote_path'] ?? '/');
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<string>
     */
    private function manifestHeader(array $profile): array
    {
        /** @var array<string, string> $columns */
        $columns = $profile['columns'] ?? [];

        return array_keys($columns);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<string>
     */
    private function manifestLine(array $profile, AssetMetadata $row, string $filename): array
    {
        /** @var array<string, string> $columns */
        $columns = $profile['columns'] ?? [];
        $categories = is_array($profile['categories'] ?? null) ? $profile['categories'] : [];

        $values = [
            'filename' => $filename,
            'title' => (string) ($row->title ?? ''),
            'description' => (string) ($row->description ?? ''),
            'category' => $this->categoryId($row->category, $categories),
            'keywords' => implode(', ', $row->keywords ?? []),
        ];

        return array_map(
            static fn (string $source): string => $values[$source] ?? '',
            array_values($columns),
        );
    }

    /**
     * Map our category slug to the marketplace's numeric id. An unmapped slug
     * falls back to the profile default rather than sending an empty cell the
     * marketplace would reject.
     *
     * @param  array<string, mixed>  $categories
     */
    private function categoryId(?string $category, array $categories): string
    {
        $slug = $category !== null ? trim($category) : '';

        if ($slug !== '' && isset($categories[$slug])) {
            return (string) $categories[$slug];
        }

        return isset($categories['default_category']) ? (string) $categories['default_category'] : '';
    }

    /**
     * @return Collection<int, AssetMetadata>
     */
    private function finalizedRows(Folder $folder): Collection
    {
        return AssetMetadata::query()
            ->where('status', MetadataStatus::Finalized)
            ->whereHas('file', fn ($q) => $q->where('folder_id', $folder->id))
            ->with('file')
            ->orderBy('created_at')
            ->get();
    }

    private function isSafeFilename(string $name): bool
    {
        return $name !== '' && preg_match(self::SAFE_FILENAME, $name) === 1;
    }

    /**
     * @param  list<list<string>>  $lines
     */
    private function renderCsv(array $lines): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents === false ? '' : $contents;
    }
}
