<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Enums\Visibility;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;

/**
 * Drive-lite link sharing: private | anyone_with_link.
 * Folder share grants browse + download for descendants (inherit).
 *
 * Guest public access and enabling link share require the storage owner
 * (user_id / quota pool) to have active paid File Hosting — free and grace deny.
 */
class VisibilityService
{
    public function __construct(
        private readonly QuotaService $quota,
        private readonly HashBlocklistService $hashBlocklist,
    ) {}

    public function folderAccessible(Folder $folder): bool
    {
        if (! $this->folderLinkSharedChain($folder)) {
            return false;
        }

        return $this->quota->ownerCanPublicShare((string) $folder->user_id);
    }

    public function fileAccessible(HostedFile $file): bool
    {
        // A checksum-blocked file is never publicly accessible (DMCA): deny as
        // 404 (share_not_found) regardless of paid gate or share link.
        if ($this->hashBlocklist->isFileBlocked($file)) {
            return false;
        }

        if (! $this->fileLinkSharedChain($file)) {
            return false;
        }

        return $this->quota->ownerCanPublicShare((string) $file->user_id);
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

    /**
     * Block free/grace owners from enabling anyone_with_link (private always allowed).
     */
    public function assertCanEnableLinkShare(string $ownerUserId, ?string $checksum = null): void
    {
        if (! $this->quota->ownerCanPublicShare($ownerUserId)) {
            throw new FileHostingException(__('api/file-hosting.public_share_requires_paid'), 422);
        }

        if ($this->hashBlocklist->isBlocked($checksum)) {
            throw new FileHostingException(__('api/file-hosting.public_share_blocked_hash'), 422);
        }
    }

    public function assertVisibilityChangeAllowed(Visibility $visibility, string $ownerUserId, ?string $checksum = null): void
    {
        if ($visibility->isLinkShared()) {
            $this->assertCanEnableLinkShare($ownerUserId, $checksum);
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

        if (! $this->quota->ownerCanPublicShare((string) $file->user_id)) {
            return null;
        }

        return route('file-hosting.public.files.show', ['file' => $file->id]);
    }

    public function folderShareUrl(Folder $folder): ?string
    {
        if (! $this->isLinkShared($folder->visibility)) {
            return null;
        }

        if (! $this->quota->ownerCanPublicShare((string) $folder->user_id)) {
            return null;
        }

        return route('file-hosting.public.folders.show', ['folder' => $folder->id]);
    }

    /**
     * Link/inherit chain only (ignores paid gate). Used for guest access composition.
     */
    private function folderLinkSharedChain(Folder $folder): bool
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

    private function fileLinkSharedChain(HostedFile $file): bool
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

        return $this->folderLinkSharedChain($folder);
    }

    private function isLinkShared(mixed $visibility): bool
    {
        if ($visibility instanceof Visibility) {
            return $visibility->isLinkShared();
        }

        return (string) $visibility === Visibility::AnyoneWithLink->value;
    }
}
