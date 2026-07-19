<?php

namespace App\Domain\FileHosting\Support;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Product activity feed for collaborative workspaces (`log_name: file_hosting.workspace`).
 */
class WorkspaceActivityRecorder
{
    public const LOG_NAME = 'file_hosting.workspace';

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        Workspace $workspace,
        ?User $causer = null,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        $props = array_merge([
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
        ], $properties);

        $logger = activity()
            ->useLog(self::LOG_NAME)
            ->event($event)
            ->withProperties($props);

        if ($subject !== null) {
            $logger->performedOn($subject);
        } else {
            $logger->performedOn($workspace);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        $logger->log($event);
    }

    public function workspaceCreated(Workspace $workspace, User $causer): void
    {
        $this->record('workspace_created', $workspace, $causer);
    }

    public function workspaceArchived(Workspace $workspace, User $causer): void
    {
        $this->record('workspace_archived', $workspace, $causer);
    }

    public function memberInvited(Workspace $workspace, User $causer, string $email, string $role): void
    {
        $this->record('member_invited', $workspace, $causer, null, [
            'email' => $email,
            'role' => $role,
        ]);
    }

    public function memberJoined(Workspace $workspace, User $member, string $role): void
    {
        $this->record('member_joined', $workspace, $member, $member, [
            'role' => $role,
        ]);
    }

    public function memberRoleChanged(Workspace $workspace, User $causer, User $target, string $from, string $to): void
    {
        $this->record('member_role_changed', $workspace, $causer, $target, [
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function memberRemoved(Workspace $workspace, User $causer, User $target): void
    {
        $this->record('member_removed', $workspace, $causer, $target);
    }

    public function fileCreated(Workspace $workspace, User $causer, HostedFile $file): void
    {
        $this->record('file_created', $workspace, $causer, $file, [
            'name' => $file->name,
            'size' => $file->size,
        ]);
    }

    public function fileRenamed(Workspace $workspace, User $causer, HostedFile $file, string $from, string $to): void
    {
        $this->record('file_renamed', $workspace, $causer, $file, [
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function fileMoved(Workspace $workspace, User $causer, HostedFile $file, ?string $fromFolderId, ?string $toFolderId): void
    {
        $this->record('file_moved', $workspace, $causer, $file, [
            'from_folder_id' => $fromFolderId,
            'to_folder_id' => $toFolderId,
            'name' => $file->name,
        ]);
    }

    public function fileVisibilityChanged(Workspace $workspace, User $causer, HostedFile $file, string $from, string $to): void
    {
        $this->record('file_visibility_changed', $workspace, $causer, $file, [
            'from' => $from,
            'to' => $to,
            'name' => $file->name,
        ]);
    }

    public function fileSoftDeleted(Workspace $workspace, User $causer, HostedFile $file): void
    {
        $this->record('file_soft_deleted', $workspace, $causer, $file, [
            'name' => $file->name,
        ]);
    }

    public function fileRestored(Workspace $workspace, User $causer, HostedFile $file): void
    {
        $this->record('file_restored', $workspace, $causer, $file, [
            'name' => $file->name,
        ]);
    }

    public function fileForceDeleted(Workspace $workspace, User $causer, HostedFile $file): void
    {
        $this->record('file_force_deleted', $workspace, $causer, $file, [
            'name' => $file->name,
        ]);
    }

    public function folderCreated(Workspace $workspace, User $causer, Folder $folder): void
    {
        $this->record('folder_created', $workspace, $causer, $folder, [
            'name' => $folder->name,
        ]);
    }

    public function folderRenamed(Workspace $workspace, User $causer, Folder $folder, string $from, string $to): void
    {
        $this->record('folder_renamed', $workspace, $causer, $folder, [
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function folderMoved(Workspace $workspace, User $causer, Folder $folder, ?string $fromParentId, ?string $toParentId): void
    {
        $this->record('folder_moved', $workspace, $causer, $folder, [
            'from_parent_id' => $fromParentId,
            'to_parent_id' => $toParentId,
            'name' => $folder->name,
        ]);
    }

    public function folderVisibilityChanged(Workspace $workspace, User $causer, Folder $folder, string $from, string $to): void
    {
        $this->record('folder_visibility_changed', $workspace, $causer, $folder, [
            'from' => $from,
            'to' => $to,
            'name' => $folder->name,
        ]);
    }

    public function folderSoftDeleted(Workspace $workspace, User $causer, Folder $folder): void
    {
        $this->record('folder_soft_deleted', $workspace, $causer, $folder, [
            'name' => $folder->name,
        ]);
    }

    public function folderRestored(Workspace $workspace, User $causer, Folder $folder): void
    {
        $this->record('folder_restored', $workspace, $causer, $folder, [
            'name' => $folder->name,
        ]);
    }

    public function folderForceDeleted(Workspace $workspace, User $causer, Folder $folder): void
    {
        $this->record('folder_force_deleted', $workspace, $causer, $folder, [
            'name' => $folder->name,
        ]);
    }
}
