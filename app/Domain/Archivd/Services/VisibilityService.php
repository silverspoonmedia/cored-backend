<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Enums\Visibility;
use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Support\FolderAncestorChain;
use App\Domain\Archivd\Support\PublicShareUrl;

/**
 * Drive-lite link sharing: private | anyone_with_link.
 * Folder share grants browse + download for descendants (inherit).
 *
 * Guest public access and enabling link share require the storage owner
 * (user_id / quota pool) to have active paid Archivd — free and grace deny.
 */
class VisibilityService
{
    public function __construct(
        private readonly QuotaService $quota,
        private readonly HashBlocklistService $hashBlocklist,
    ) {}

    public function folderAccessible(Folder $folder): bool
    {
        // System workspaces (microstock, …) are never publicly accessible.
        if ($folder->workspace?->isSystem()) {
            return false;
        }

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

        // System workspaces (microstock, …) are never publicly accessible.
        if ($file->workspace?->isSystem()) {
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
            throw new ArchivdException(__('api/archivd.share_not_found'), 404);
        }
    }

    public function assertGuestCanReadFile(HostedFile $file): void
    {
        if (! $this->fileAccessible($file)) {
            throw new ArchivdException(__('api/archivd.share_not_found'), 404);
        }
    }

    /**
     * Block free/grace owners from enabling anyone_with_link (private always allowed).
     */
    public function assertCanEnableLinkShare(string $ownerUserId, ?string $checksum = null): void
    {
        if (! $this->quota->ownerCanPublicShare($ownerUserId)) {
            throw new ArchivdException(__('api/archivd.public_share_requires_paid'), 422);
        }

        if ($this->hashBlocklist->isBlocked($checksum)) {
            throw new ArchivdException(__('api/archivd.public_share_blocked_hash'), 422);
        }
    }

    public function assertVisibilityChangeAllowed(Visibility $visibility, string $ownerUserId, ?string $checksum = null): void
    {
        if ($visibility->isLinkShared()) {
            $this->assertCanEnableLinkShare($ownerUserId, $checksum);
        }
    }

    /**
     * Built-in system workspaces (microstock, …) never allow public link share —
     * their files/folders stay private by product rule. Personal and team
     * workspaces are unaffected.
     */
    public function assertSystemWorkspaceShareAllowed(?Workspace $workspace): void
    {
        if ($workspace !== null && $workspace->isSystem()) {
            throw new ArchivdException(__('api/archivd.public_share_not_allowed'), 422);
        }
    }

    public function normalize(string $visibility): Visibility
    {
        $value = Visibility::tryFrom($visibility);

        if ($value === null) {
            throw new ArchivdException(__('api/archivd.invalid_visibility'));
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

        return PublicShareUrl::file((string) $file->id);
    }

    public function folderShareUrl(Folder $folder): ?string
    {
        if (! $this->isLinkShared($folder->visibility)) {
            return null;
        }

        if (! $this->quota->ownerCanPublicShare((string) $folder->user_id)) {
            return null;
        }

        return PublicShareUrl::folder((string) $folder->id);
    }

    /**
     * Link/inherit chain only (ignores paid gate). Used for guest access composition.
     */
    private function folderLinkSharedChain(Folder $folder): bool
    {
        if ($this->isLinkShared($folder->visibility)) {
            return true;
        }

        $byId = FolderAncestorChain::indexForOwner((string) $folder->user_id);
        $parentId = $folder->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard < 1000) {
            $parent = $byId->get($parentId);

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
