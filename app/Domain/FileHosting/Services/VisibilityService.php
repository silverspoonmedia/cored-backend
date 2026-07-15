<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Enums\Visibility;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;

/**
 * Drive-lite link sharing: private | anyone_with_link.
 * Folder share grants browse + download for descendants (inherit).
 */
class VisibilityService
{
    public function folderAccessible(Folder $folder): bool
    {
        if ($this->isLinkShared($folder->visibility)) {
            return true;
        }

        $parentId = $folder->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard < 1000) {
            $parent = Folder::query()
                ->whereKey($parentId)
                ->where('user_id', $folder->user_id)
                ->first(['id', 'parent_id', 'visibility']);

            if ($parent === null) {
                break;
            }

            if ($this->isLinkShared($parent->visibility)) {
                return true;
            }

            $parentId = $parent->parent_id;
            $guard++;
        }

        return false;
    }

    public function fileAccessible(HostedFile $file): bool
    {
        if ($this->isLinkShared($file->visibility)) {
            return true;
        }

        if ($file->folder_id === null) {
            return false;
        }

        $folder = $file->relationLoaded('folder')
            ? $file->folder
            : Folder::query()->whereKey($file->folder_id)->first();

        if ($folder === null) {
            return false;
        }

        return $this->folderAccessible($folder);
    }

    public function assertGuestCanReadFolder(Folder $folder): void
    {
        if (! $this->folderAccessible($folder)) {
            throw new FileHostingException(__('api/file-hosting.share_not_found'), 404);
        }
    }

    public function assertGuestCanReadFile(HostedFile $file): void
    {
        if (! $this->fileAccessible($file)) {
            throw new FileHostingException(__('api/file-hosting.share_not_found'), 404);
        }
    }

    public function normalize(string $visibility): Visibility
    {
        $value = Visibility::tryFrom($visibility);

        if ($value === null) {
            throw new FileHostingException(__('api/file-hosting.invalid_visibility'));
        }

        return $value;
    }

    public function fileShareUrl(HostedFile $file): ?string
    {
        if (! $this->isLinkShared($file->visibility)) {
            return null;
        }

        return route('file-hosting.public.files.show', ['file' => $file->id]);
    }

    public function folderShareUrl(Folder $folder): ?string
    {
        if (! $this->isLinkShared($folder->visibility)) {
            return null;
        }

        return route('file-hosting.public.folders.show', ['folder' => $folder->id]);
    }

    private function isLinkShared(mixed $visibility): bool
    {
        if ($visibility instanceof Visibility) {
            return $visibility->isLinkShared();
        }

        return (string) $visibility === Visibility::AnyoneWithLink->value;
    }
}
