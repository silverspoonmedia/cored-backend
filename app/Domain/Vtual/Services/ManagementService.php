<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Notification\Notifications\NotificationType;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\ManagementInvite;
use App\Domain\Vtual\Models\ManagementMember;
use App\Domain\Vtual\Notifications\ManagementMemberInvited;
use App\Domain\Vtual\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementService
{
    public function __construct(
        private readonly ManagementAccessService $access,
        private readonly CreatorAccessService $creatorAccess,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return Collection<int, Management>
     */
    public function listForUser(User $actor): Collection
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return Management::query()
                ->where('status', EntityStatus::Active)
                ->orderBy('name')
                ->get();
        }

        $ids = ManagementMember::query()
            ->where('user_id', $actor->id)
            ->pluck('management_id');

        return Management::query()
            ->whereIn('id', $ids)
            ->where('status', EntityStatus::Active)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, logo_url?: string|null}  $data
     */
    public function create(User $actor, array $data): Management
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new VtualException(__('api/vtual.management.name_required'), 422);
        }

        $baseSlug = isset($data['slug']) && is_string($data['slug']) && trim($data['slug']) !== ''
            ? trim($data['slug'])
            : $name;

        return DB::transaction(function () use ($actor, $data, $name, $baseSlug): Management {
            $slug = SlugGenerator::unique($baseSlug, fn (string $s): bool => Management::query()
                ->where('slug', $s)
                ->exists());

            $management = Management::query()->create([
                'slug' => $slug,
                'name' => $name,
                'description' => $data['description'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'status' => EntityStatus::Active,
            ]);

            ManagementMember::query()->create([
                'management_id' => $management->id,
                'user_id' => $actor->id,
                'role' => ManagementMemberRole::Owner,
            ]);

            return $management->load('members.user');
        });
    }

    public function show(User $actor, string $managementId): Management
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanView($management, $actor);

        return $management->load([
            'members.user',
            'creatorAssignments' => fn ($q) => $q->whereNull('left_at')->with('creator'),
        ]);
    }

    /**
     * Guest-safe public profile: active management, roster limited to
     * publicly listed creators (active + at least one active public channel).
     */
    public function showForPublic(string $slugOrUuid): Management
    {
        $query = Management::query()->where('status', EntityStatus::Active);

        if (\Illuminate\Support\Str::isUuid($slugOrUuid)) {
            $query->whereKey($slugOrUuid);
        } else {
            $query->where('slug', $slugOrUuid);
        }

        $management = $query->first();

        if ($management === null) {
            throw new VtualException(__('api/vtual.management.not_found'), 404);
        }

        return $management->load([
            'externalLinks' => fn ($q) => $q->orderBy('sort_order'),
            'creatorAssignments' => fn ($q) => $q->whereNull('left_at')
                ->with(['creator' => fn ($cq) => $cq
                    ->where('status', EntityStatus::Active)
                    ->whereHas('channels', function ($chq): void {
                        $chq->where('status', \App\Domain\Vtual\Enums\ChannelStatus::Active)
                            ->where('visibility', \App\Domain\Vtual\Enums\ChannelVisibility::Public);
                    })
                    ->with(['channels' => fn ($chq) => $chq
                        ->where('status', \App\Domain\Vtual\Enums\ChannelStatus::Active)
                        ->where('visibility', \App\Domain\Vtual\Enums\ChannelVisibility::Public)
                        ->with('platform'),
                    ]),
                ]),
        ]);
    }

    /**
     * @param  array{name?: string, slug?: string, description?: string|null, logo_url?: string|null, status?: string}  $data
     */
    public function update(User $actor, string $managementId, array $data): Management
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanMutate($management, $actor);

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new VtualException(__('api/vtual.management.name_required'), 422);
            }
            $management->name = $name;
        }

        if (array_key_exists('slug', $data) && is_string($data['slug']) && trim($data['slug']) !== '') {
            $slug = SlugGenerator::from(trim($data['slug']));
            $taken = Management::query()
                ->where('slug', $slug)
                ->whereKeyNot($management->id)
                ->exists();
            if ($taken) {
                throw new VtualException(__('api/vtual.management.slug_taken'), 422);
            }
            $management->slug = $slug;
        }

        if (array_key_exists('description', $data)) {
            $management->description = $data['description'];
        }

        if (array_key_exists('logo_url', $data)) {
            $management->logo_url = $data['logo_url'];
        }

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $management->status = EntityStatus::from($data['status']);
        }

        $management->save();

        return $management->refresh()->load('members.user');
    }

    /**
     * Attach a creator to this management. Actor must manage the org and mutate the creator.
     */
    public function assignCreator(User $actor, string $managementId, string $creatorId): ManagementCreator
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        $creator = Creator::query()->findOrFail($creatorId);
        $this->creatorAccess->assertCanMutate($creator, $actor);

        /** @var ManagementCreator $assignment */
        $assignment = ManagementCreator::query()->firstOrNew([
            'management_id' => $management->id,
            'creator_id' => $creator->id,
        ]);

        if ($assignment->exists && $assignment->left_at === null) {
            return $assignment->load('creator');
        }

        $assignment->joined_at = now();
        $assignment->left_at = null;
        $assignment->save();

        return $assignment->load('creator');
    }

    public function unassignCreator(User $actor, string $managementId, string $creatorId): ManagementCreator
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        /** @var ManagementCreator|null $assignment */
        $assignment = ManagementCreator::query()
            ->where('management_id', $management->id)
            ->where('creator_id', $creatorId)
            ->whereNull('left_at')
            ->first();

        if ($assignment === null) {
            throw new VtualException(__('api/vtual.management.assignment_not_found'), 404);
        }

        $assignment->left_at = now();
        $assignment->save();

        return $assignment->load('creator');
    }

    /**
     * @return Collection<int, ManagementMember>
     */
    public function listMembers(User $actor, string $managementId): Collection
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanView($management, $actor);

        return ManagementMember::query()
            ->where('management_id', $management->id)
            ->with('user')
            ->get();
    }

    /**
     * Send an invite-only membership invitation by email (privacy: no user listing).
     *
     * @param  array{email: string, role: string}  $data
     */
    public function inviteMember(User $actor, string $managementId, array $data): ManagementInvite
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanManageMembers($management, $actor);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role = ManagementMemberRole::tryFrom((string) ($data['role'] ?? ''));

        if ($role === null || ! in_array($role, ManagementMemberRole::assignable(), true)) {
            throw new VtualException(__('api/vtual.management.role_invalid'), 422);
        }

        /** @var User|null $invitee */
        $invitee = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($invitee === null) {
            throw new VtualException(__('api/vtual.management.invite_user_not_found'), 404);
        }

        if ((string) $invitee->id === (string) $actor->id) {
            throw new VtualException(__('api/vtual.management.cannot_edit_self'), 422);
        }

        $memberExists = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $invitee->id)
            ->exists();
        if ($memberExists) {
            throw new VtualException(__('api/vtual.management.member_already_exists'), 422);
        }

        $pendingExists = ManagementInvite::query()
            ->where('management_id', $management->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', ManagementInviteStatus::Pending->value)
            ->exists();
        if ($pendingExists) {
            throw new VtualException(__('api/vtual.management.invite_already_pending'), 422);
        }

        /** @var ManagementInvite $invite */
        $invite = ManagementInvite::query()->create([
            'management_id' => $management->id,
            'email' => $email,
            'user_id' => $invitee->id,
            'role' => $role,
            'token' => Str::random(48),
            'status' => ManagementInviteStatus::Pending,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        $invite->setRelation('management', $management);
        $invitee->notify(new ManagementMemberInvited($invite));

        $this->notifications->sendToUsers(
            NotificationType::VtualManagementInviteReceived,
            [$invitee->id],
            [
                'management' => $management->name,
                'inviter' => $actor->name,
                'role' => $role->value,
            ],
            'vtual.invites.respond',
            ['token' => $invite->token],
        );

        return $invite->load('inviter');
    }

    /**
     * @return Collection<int, ManagementInvite>
     */
    public function listInvites(User $actor, string $managementId): Collection
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanView($management, $actor);

        return ManagementInvite::query()
            ->where('management_id', $management->id)
            ->with('inviter')
            ->latest()
            ->get();
    }

    /**
     * Cancel a pending invite, or revoke an accepted one (removes membership).
     */
    public function cancelInvite(User $actor, string $managementId, string $inviteId): ManagementInvite
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanManageMembers($management, $actor);

        /** @var ManagementInvite|null $invite */
        $invite = ManagementInvite::query()
            ->where('management_id', $management->id)
            ->whereKey($inviteId)
            ->first();

        if ($invite === null) {
            throw new VtualException(__('api/vtual.management.invite_expired'), 404);
        }

        if ($invite->isPending()) {
            $invite->status = ManagementInviteStatus::Cancelled;
            $invite->responded_at = now();
            $invite->save();

            return $invite->load('inviter');
        }

        if ($invite->status === ManagementInviteStatus::Accepted) {
            DB::transaction(function () use ($management, $invite): void {
                /** @var ManagementMember|null $member */
                $member = ManagementMember::query()
                    ->where('management_id', $management->id)
                    ->where('user_id', $invite->user_id)
                    ->first();
                if ($member !== null) {
                    if ($member->role === ManagementMemberRole::Owner) {
                        $this->assertNotLastOwner($management, $member);
                    }
                    $member->delete();
                }
                $invite->status = ManagementInviteStatus::Revoked;
                $invite->save();
            });

            return $invite->refresh()->load('inviter');
        }

        throw new VtualException(__('api/vtual.management.invite_not_actionable'), 422);
    }

    public function acceptInvite(User $actor, string $token): ManagementInvite
    {
        $invite = $this->findActionableInviteOrFail($actor, $token);

        return DB::transaction(function () use ($invite, $actor): ManagementInvite {
            $memberExists = ManagementMember::query()
                ->where('management_id', $invite->management_id)
                ->where('user_id', $actor->id)
                ->exists();

            if (! $memberExists) {
                ManagementMember::query()->create([
                    'management_id' => $invite->management_id,
                    'user_id' => $actor->id,
                    'role' => $invite->role,
                ]);
            }

            $invite->status = ManagementInviteStatus::Accepted;
            $invite->responded_at = now();
            $invite->save();

            return $invite->load('management');
        });
    }

    public function declineInvite(User $actor, string $token): ManagementInvite
    {
        $invite = $this->findActionableInviteOrFail($actor, $token);

        $invite->status = ManagementInviteStatus::Declined;
        $invite->responded_at = now();
        $invite->save();

        return $invite->load('management');
    }

    /**
     * Prefill for the SPA respond screen — same guards as accept/decline, no mutation.
     */
    public function showInviteByToken(User $actor, string $token): ManagementInvite
    {
        $invite = $this->findActionableInviteOrFail($actor, $token);

        return $invite->load('management', 'inviter');
    }

    /**
     * Resolve invite by token with pending/expiry + account-match guards (no mutation).
     */
    private function findActionableInviteOrFail(User $actor, string $token): ManagementInvite
    {
        /** @var ManagementInvite|null $invite */
        $invite = ManagementInvite::query()->where('token', $token)->first();
        if ($invite === null) {
            throw new VtualException(__('api/vtual.management.invite_expired'), 404);
        }

        if (! $invite->isPending() || $invite->isExpired()) {
            throw new VtualException(__('api/vtual.management.invite_expired'), 422);
        }

        if (strcasecmp((string) $invite->email, (string) $actor->email) !== 0) {
            throw new VtualException(__('api/vtual.management.invite_wrong_account'), 403);
        }

        return $invite;
    }

    /**
     * @param  array{role: string}  $data
     */
    public function updateMemberRole(User $actor, string $managementId, string $memberId, array $data): ManagementMember
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanManageMembers($management, $actor);

        $member = $this->findMemberOrFail($management, $memberId);

        if ((string) $member->user_id === (string) $actor->id) {
            throw new VtualException(__('api/vtual.management.cannot_edit_self'), 422);
        }

        $role = ManagementMemberRole::tryFrom((string) ($data['role'] ?? ''));
        if ($role === null || ! in_array($role, ManagementMemberRole::assignable(), true)) {
            throw new VtualException(__('api/vtual.management.role_invalid'), 422);
        }

        if ($member->role === ManagementMemberRole::Owner) {
            $this->assertNotLastOwner($management, $member);
        }

        $member->role = $role;
        $member->save();

        return $member->load('user');
    }

    public function removeMember(User $actor, string $managementId, string $memberId): void
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanManageMembers($management, $actor);

        $member = $this->findMemberOrFail($management, $memberId);

        if ((string) $member->user_id === (string) $actor->id) {
            throw new VtualException(__('api/vtual.management.cannot_edit_self'), 422);
        }

        if ($member->role === ManagementMemberRole::Owner) {
            $this->assertNotLastOwner($management, $member);
        }

        $member->delete();
    }

    private function findMemberOrFail(Management $management, string $memberId): ManagementMember
    {
        /** @var ManagementMember|null $member */
        $member = ManagementMember::query()
            ->where('management_id', $management->id)
            ->whereKey($memberId)
            ->first();

        if ($member === null) {
            throw new VtualException(__('api/vtual.management.member_not_found'), 404);
        }

        return $member;
    }

    private function assertNotLastOwner(Management $management, ManagementMember $target): void
    {
        $ownerCount = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('role', ManagementMemberRole::Owner)
            ->count();

        if ($ownerCount <= 1) {
            throw new VtualException(__('api/vtual.management.last_owner'), 422);
        }
    }
}
