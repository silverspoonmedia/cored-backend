<?php

namespace App\Domain\FileHosting\Support;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use Illuminate\Support\Collection;

/**
 * Sibling display-name allocation for files and folders.
 *
 * Soft-deleted rows are ignored (default SoftDeletes scope). S3 object keys
 * are never renamed — this is DB display name only.
 *
 * Rules (preferred = original name, e.g. "A.zip" or "Docs"):
 * - If preferred free → use it.
 * - Else → "{stem} ({maxN+1}).{ext}" where maxN is the highest active index among
 *   preferred itself (counts as 1) and siblings matching "{stem} (N).{ext}".
 *   Example: A.zip + A (3).zip active → next is A (4).zip even if (2) was deleted.
 * - Soft-deleted base free again → preferred reused.
 */
class UniqueDisplayNameAllocator
{
    /**
     * Free sibling name among hosted files in the same owner + folder + workspace.
     *
     * @param  string|null  $ignoreFileId  Exclude this file (e.g. the row being moved/renamed).
     */
    public function allocateFile(
        string $ownerId,
        ?string $folderId,
        string $preferred,
        ?string $workspaceId = null,
        ?string $ignoreFileId = null,
    ): string {
        return $this->allocate(
            $preferred,
            fn (string $name): bool => $this->fileNameExists($ownerId, $folderId, $name, $workspaceId, $ignoreFileId),
            fn (): Collection => $this->fileSiblingNames($ownerId, $folderId, $workspaceId, $ignoreFileId),
            fileCollision: true,
        );
    }

    /**
     * Free sibling name among folders in the same owner + parent + workspace.
     *
     * @param  string|null  $ignoreFolderId  Exclude this folder (e.g. the row being moved).
     */
    public function allocateFolder(
        string $ownerId,
        ?string $parentId,
        string $preferred,
        ?string $workspaceId = null,
        ?string $ignoreFolderId = null,
    ): string {
        return $this->allocate(
            $preferred,
            fn (string $name): bool => $this->folderNameExists($ownerId, $parentId, $name, $workspaceId, $ignoreFolderId),
            fn (): Collection => $this->folderSiblingNames($ownerId, $parentId, $workspaceId, $ignoreFolderId),
            fileCollision: false,
        );
    }

    /**
     * @param  callable(string): bool  $exists
     * @param  callable(): Collection<int, string>  $siblingNames
     */
    private function allocate(
        string $preferred,
        callable $exists,
        callable $siblingNames,
        bool $fileCollision,
    ): string {
        $preferred = trim($preferred);

        if ($preferred === '') {
            throw new FileHostingException(
                $fileCollision
                    ? __('api/file-hosting.upload.invalid_name')
                    : __('api/file-hosting.folder_name_taken'),
            );
        }

        if (! $exists($preferred)) {
            return $preferred;
        }

        [$stem, $ext] = $this->splitDisplayName($preferred);
        $maxSequence = $this->maxDisplayNameSequence($siblingNames(), $stem, $ext);
        $next = max(2, $maxSequence + 1);

        for ($n = $next; $n < $next + 1000; $n++) {
            $candidate = $this->formatNumberedDisplayName($stem, $ext, $n);

            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        throw new FileHostingException(
            $fileCollision
                ? __('api/file-hosting.file_name_taken')
                : __('api/file-hosting.folder_name_taken'),
        );
    }

    private function fileNameExists(
        string $ownerId,
        ?string $folderId,
        string $name,
        ?string $workspaceId,
        ?string $ignoreFileId,
    ): bool {
        $query = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreFileId !== null) {
            $query->whereKeyNot($ignoreFileId);
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, string>
     */
    private function fileSiblingNames(
        string $ownerId,
        ?string $folderId,
        ?string $workspaceId,
        ?string $ignoreFileId,
    ): Collection {
        $query = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId);

        if ($ignoreFileId !== null) {
            $query->whereKeyNot($ignoreFileId);
        }

        return $query->pluck('name');
    }

    private function folderNameExists(
        string $ownerId,
        ?string $parentId,
        string $name,
        ?string $workspaceId,
        ?string $ignoreFolderId,
    ): bool {
        $query = Folder::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreFolderId !== null) {
            $query->whereKeyNot($ignoreFolderId);
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, string>
     */
    private function folderSiblingNames(
        string $ownerId,
        ?string $parentId,
        ?string $workspaceId,
        ?string $ignoreFolderId,
    ): Collection {
        $query = Folder::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('parent_id', $parentId);

        if ($ignoreFolderId !== null) {
            $query->whereKeyNot($ignoreFolderId);
        }

        return $query->pluck('name');
    }

    /**
     * Highest sequence in the "{stem}" / "{stem} (N)" family (same extension).
     * Base name counts as 1 when present. Soft-deleted ignored by caller query.
     *
     * @param  Collection<int, mixed>  $names
     * @return int 0 when no family members exist
     */
    private function maxDisplayNameSequence(Collection $names, string $stem, string $ext): int
    {
        $base = $this->formatNumberedDisplayName($stem, $ext, null);
        $max = 0;

        $quotedStem = preg_quote($stem, '/');
        $pattern = $ext === ''
            ? '/^'.$quotedStem.' \((\d+)\)$/iu'
            : '/^'.$quotedStem.' \((\d+)\)\.'.preg_quote($ext, '/').'$/iu';

        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (mb_strtolower($name) === mb_strtolower($base)) {
                $max = max($max, 1);

                continue;
            }

            if (preg_match($pattern, $name, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    /**
     * @return array{0: string, 1: string} stem, extension without leading dot (may be empty)
     */
    private function splitDisplayName(string $name): array
    {
        $ext = (string) pathinfo($name, PATHINFO_EXTENSION);
        $stem = (string) pathinfo($name, PATHINFO_FILENAME);

        return [$stem, $ext];
    }

    /**
     * @param  int|null  $n  null = base name without " (N)"
     */
    private function formatNumberedDisplayName(string $stem, string $ext, ?int $n): string
    {
        $body = $n === null ? $stem : $stem.' ('.$n.')';

        return $ext === '' ? $body : $body.'.'.$ext;
    }
}
