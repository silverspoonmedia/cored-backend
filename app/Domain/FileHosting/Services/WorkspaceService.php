<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Enums\WorkspaceInviteStatus;
use App\Domain\FileHosting\Enums\WorkspaceMemberStatus;
use App\Domain\FileHosting\Enums\WorkspaceRole;
use App\Domain\FileHosting\Enums\WorkspaceStatus;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Models\WorkspaceInvite;
use App\Domain\FileHosting\Models\WorkspaceMember;
use App\Domain\FileHosting\Support\FileHostingRealtime;
use App\Domain\FileHosting\Support\WorkspaceActivityRecorder;
use App\Domain\Identity\Models\User;
use App\Support\Product\Services\ProductAnalyticsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceActivityRecorder $activity,
        private readonly FileHostingRealtime $realtime,
    ) {}

    /**
     * Active workspaces the actor owns or is an active member of (switcher source).
     * Owning one workspace does not exclude membership in others.
     *
     * @return Collection<int, Workspace>
     */
    public function listForUser(User $actor): Collection
    {
        $ids = WorkspaceMember::query()
            ->where('user_id', $actor->id)
            ->where('status', WorkspaceMemberStatus::Active)
            ->pluck('workspace_id');

        return Workspace::query()
            ->where('status', WorkspaceStatus::Active)
            ->where(function ($q) use ($actor, $ids): void {
                $q->where('owner_user_id', $actor->id)
                    ->orWhereIn('id', $ids);
            })
            ->with('owner')
            ->orderBy('name')
            ->get();
    }

    public function create(User $actor, string $name): Workspace
    {
        $this->workspaceAccess->assertCanCreateOwnedWorkspace($actor->id);

        $name = trim($name);
        if ($name === '') {
            throw new FileHostingException(__('api/file-hosting.workspace.name_required'), 422);
        }

        return DB::transaction(function () use ($actor, $name): Workspace {
            // Serialize slot checks per owner (concurrent create at cap).
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->workspaceAccess->assertCanCreateOwnedWorkspace($actor->id);

            // Multi-own: always insert. Archive frees a slot; no reactivate-first-archived.
            $workspace = Workspace::query()->create([
                'owner_user_id' => $actor->id,
                'name' => $name,
                'status' => WorkspaceStatus::Active,
            ]);

            WorkspaceMember::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $actor->id,
                'role' => WorkspaceRole::Owner,
                'status' => WorkspaceMemberStatus::Active,
                'invited_by' => null,
                'joined_at' => now(),
            ]);

            $this->activity->workspaceCreated($workspace, $actor);

            app(ProductAnalyticsService::class)
                ->increment('FileHosting', $actor->id, 'workspace_count_owned', 1);

            return $workspace->load('owner');
        });
    }

    public function show(User $actor, string $workspaceId): Workspace
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $this->workspaceAccess->assertCanView($workspace, $actor);

        return $workspace->load(['owner', 'activeMembers.user']);
    }

    public function archive(User $actor, string $workspaceId): Workspace
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $role = $this->workspaceAccess->roleFor($workspace, $actor);

        if ($role !== WorkspaceRole::Owner) {
            throw new FileHostingException(__('api/file-hosting.workspace.owner_only'), 403);
        }

        if ($workspace->isArchived()) {
            return $workspace;
        }

        $workspace->status = WorkspaceStatus::Archived;
        $workspace->save();

        // Block complete on in-flight uploads; best-effort S3 abort.
        app(DirectUploadService::class)->abortPendingForWorkspace($workspace->id);

        $this->activity->workspaceArchived($workspace, $actor);
        $this->realtime->membersChanged((string) $workspace->id, 'archived', actorId: $actor->id);
        $this->realtime->activityAppended((string) $workspace->id, $actor->id);

        return $workspace->refresh();
    }

    /**
     * @return Collection<int, WorkspaceMember>
     */
    public function listMembers(User $actor, string $workspaceId): Collection
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $this->workspaceAccess->assertCanView($workspace, $actor);

        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceMemberStatus::Active)
            ->with('user')
            ->orderBy('role')
            ->get();
    }

    public function invite(User $actor, string $workspaceId, string $email, string $role): WorkspaceInvite
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $this->workspaceAccess->assertCanManageMembers($workspace, $actor);
        $this->workspaceAccess->assertOwnerEligibleForCollab($workspace->owner_user_id);

        $roleEnum = WorkspaceRole::tryFrom($role);
        if ($roleEnum === null || ! in_array($roleEnum, WorkspaceRole::assignable(), true)) {
            throw new FileHostingException(__('api/file-hosting.workspace.invalid_role'), 422);
        }

        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new FileHostingException(__('api/file-hosting.workspace.invalid_email'), 422);
        }

        if (strcasecmp($email, (string) $actor->email) === 0) {
            throw new FileHostingException(__('api/file-hosting.workspace.cannot_invite_self'), 422);
        }

        $target = User::query()->where('email', $email)->first();
        if ($target !== null) {
            $existing = $this->workspaceAccess->activeMember($workspace, $target->id);
            if ($existing !== null) {
                throw new FileHostingException(__('api/file-hosting.workspace.already_member'), 422);
            }
        }

        $pending = WorkspaceInvite::query()
            ->where('workspace_id', $workspace->id)
            ->where('email', $email)
            ->where('status', WorkspaceInviteStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        if ($pending !== null) {
            throw new FileHostingException(__('api/file-hosting.workspace.invite_pending'), 422);
        }

        $invite = WorkspaceInvite::query()->create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'user_id' => $target?->id,
            'role' => $roleEnum,
            'token' => Str::random(48),
            'status' => WorkspaceInviteStatus::Pending,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->activity->memberInvited($workspace, $actor, $email, $roleEnum->value);
        $this->realtime->membersChanged((string) $workspace->id, 'invited', userId: $target?->id, actorId: $actor->id);
        $this->realtime->activityAppended((string) $workspace->id, $actor->id);

        return $invite->load(['workspace', 'inviter']);
    }

    public function acceptInvite(User $actor, string $token): WorkspaceMember
    {
        $invite = WorkspaceInvite::query()
            ->where('token', $token)
            ->first();

        if ($invite === null) {
            throw new FileHostingException(__('api/file-hosting.workspace.invite_not_found'), 404);
        }

        if ($invite->isExpired()) {
            $invite->status = WorkspaceInviteStatus::Expired;
            $invite->save();
            throw new FileHostingException(__('api/file-hosting.workspace.invite_expired'), 422);
        }

        if ($invite->status !== WorkspaceInviteStatus::Pending) {
            throw new FileHostingException(__('api/file-hosting.workspace.invite_not_pending'), 422);
        }

        if (strcasecmp($invite->email, (string) $actor->email) !== 0) {
            throw new FileHostingException(__('api/file-hosting.workspace.invite_email_mismatch'), 403);
        }

        $workspace = $this->workspaceAccess->findOrFail($invite->workspace_id);
        if (! $workspace->isActive()) {
            throw new FileHostingException(__('api/file-hosting.workspace.archived'), 422);
        }

        $this->workspaceAccess->assertOwnerEligibleForCollab($workspace->owner_user_id);

        $member = DB::transaction(function () use ($actor, $invite, $workspace): WorkspaceMember {
            $member = WorkspaceMember::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $actor->id,
                ],
                [
                    'role' => $invite->role,
                    'status' => WorkspaceMemberStatus::Active,
                    'invited_by' => $invite->invited_by,
                    'joined_at' => now(),
                ],
            );

            $invite->status = WorkspaceInviteStatus::Accepted;
            $invite->user_id = $actor->id;
            $invite->accepted_at = now();
            $invite->save();

            $this->activity->memberJoined($workspace, $actor, $invite->role->value);

            return $member->load('user');
        });

        $this->realtime->membersChanged((string) $workspace->id, 'joined', userId: $actor->id, actorId: $actor->id);
        $this->realtime->activityAppended((string) $workspace->id, $actor->id);

        return $member;
    }

    public function updateMemberRole(User $actor, string $workspaceId, string $memberUserId, string $role): WorkspaceMember
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $this->workspaceAccess->assertCanManageMembers($workspace, $actor);

        if ($memberUserId === $workspace->owner_user_id) {
            throw new FileHostingException(__('api/file-hosting.workspace.cannot_change_owner_role'), 422);
        }

        $roleEnum = WorkspaceRole::tryFrom($role);
        if ($roleEnum === null || ! in_array($roleEnum, WorkspaceRole::assignable(), true)) {
            throw new FileHostingException(__('api/file-hosting.workspace.invalid_role'), 422);
        }

        $member = $this->workspaceAccess->activeMember($workspace, $memberUserId);
        if ($member === null) {
            throw new FileHostingException(__('api/file-hosting.workspace.member_not_found'), 404);
        }

        $from = $member->role->value;
        $member->role = $roleEnum;
        $member->save();

        $target = User::query()->findOrFail($memberUserId);
        $this->activity->memberRoleChanged($workspace, $actor, $target, $from, $roleEnum->value);
        $this->realtime->membersChanged((string) $workspace->id, 'role_changed', userId: $memberUserId, actorId: $actor->id);
        $this->realtime->activityAppended((string) $workspace->id, $actor->id);

        return $member->fresh(['user']);
    }

    public function removeMember(User $actor, string $workspaceId, string $memberUserId): void
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $this->workspaceAccess->assertCanManageMembers($workspace, $actor);

        if ($memberUserId === $workspace->owner_user_id) {
            throw new FileHostingException(__('api/file-hosting.workspace.cannot_remove_owner'), 422);
        }

        $member = $this->workspaceAccess->activeMember($workspace, $memberUserId);
        if ($member === null) {
            throw new FileHostingException(__('api/file-hosting.workspace.member_not_found'), 404);
        }

        $member->status = WorkspaceMemberStatus::Removed;
        $member->save();

        $target = User::query()->findOrFail($memberUserId);
        $this->activity->memberRemoved($workspace, $actor, $target);
        $this->realtime->membersChanged((string) $workspace->id, 'removed', userId: $memberUserId, actorId: $actor->id);
        $this->realtime->activityAppended((string) $workspace->id, $actor->id);
    }

    /**
     * @return Collection<int, Activity>
     */
    public function activity(User $actor, string $workspaceId, int $limit = 50): Collection
    {
        $workspace = $this->workspaceAccess->findOrFail($workspaceId);
        $this->workspaceAccess->assertCanView($workspace, $actor);

        $limit = max(1, min(100, $limit));

        return Activity::query()
            ->where('log_name', WorkspaceActivityRecorder::LOG_NAME)
            ->where('properties->workspace_id', $workspace->id)
            ->with(['causer', 'subject'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
