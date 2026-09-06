<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Support\NavigationRealtime;
use App\Domain\Notification\Notifications\NotificationType;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorManagementInvite;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Notifications\ManagementCreatorInvited;
use App\Domain\Vtual\Support\SlugGenerator;
use App\Domain\Vtual\Support\VtualManagementRealtime;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invite-based vTuber profile linking for management orgs.
 *
 * Mode `existing` pins a creator the invitee already owns; mode `new` mints
 * an orphan profile + Owner membership for the invitee on accept. Either way
 * the invitee becomes the profile's Owner member and an ACTIVE
 * vtual_management_creators assignment links it to the org.
 */
class ManagementCreatorInviteService
{
    public function __construct(
        private readonly ManagementAccessService $access,
        private readonly CreatorService $creators,
        private readonly NotificationService $notifications,
        private readonly VtualManagementRealtime $realtime,
        private readonly ProductActivationService $activations,
        private readonly NavigationRealtime $navigationRealtime,
    ) {}

    /**
     * Profiles owned by the given email's account — candidate targets for an
     * `existing`-mode invite. Privacy-scoped: management Owner/Admin/staff only.
     *
     * @return Collection<int, Creator>
     */
    public function candidates(User $actor, string $managementId, string $email): Collection
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        $invitee = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
        if ($invitee === null) {
            throw new VtualException(__('api/vtual.management.invite_user_not_found'), 404);
        }

        $ids = CreatorMember::query()
            ->where('user_id', $invitee->id)
            ->where('role', CreatorMemberRole::Owner)
            ->pluck('creator_id');

        return Creator::query()
            ->whereIn('id', $ids)
            ->where('status', EntityStatus::Active)
            ->orderBy('display_name')
            ->get(['id', 'slug', 'display_name', 'avatar_url']);
    }

    /**
     * @param  array{email: string, mode: string, creator_id?: string|null, new_display_name?: string|null}  $data
     */
    public function inviteCreator(User $actor, string $managementId, array $data): CreatorManagementInvite
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $mode = (string) ($data['mode'] ?? '');

        if (! in_array($mode, ['existing', 'new'], true)) {
            throw new VtualException(__('api/vtual.management.creator_invite_mode_invalid'), 422);
        }

        /** @var User|null $invitee */
        $invitee = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($invitee === null) {
            throw new VtualException(__('api/vtual.management.invite_user_not_found'), 404);
        }

        $creatorId = null;
        $newDisplayName = null;

        if ($mode === 'existing') {
            $creatorId = (string) ($data['creator_id'] ?? '');
            $creator = Creator::query()->find($creatorId);
            if ($creator === null) {
                throw new VtualException(__('api/vtual.creator.not_found'), 404);
            }

            // Invitee must own the profile.
            $owns = CreatorMember::query()
                ->where('creator_id', $creator->id)
                ->where('user_id', $invitee->id)
                ->where('role', CreatorMemberRole::Owner)
                ->exists();
            if (! $owns) {
                throw new VtualException(__('api/vtual.management.creator_invite_not_owner'), 422);
            }

            // Already actively linked to this org?
            $linked = ManagementCreator::query()
                ->where('management_id', $management->id)
                ->where('creator_id', $creator->id)
                ->whereNull('left_at')
                ->exists();
            if ($linked) {
                throw new VtualException(__('api/vtual.management.creator_already_linked'), 422);
            }
        } else {
            $newDisplayName = trim((string) ($data['new_display_name'] ?? ''));
            if ($newDisplayName === '') {
                throw new VtualException(__('api/vtual.management.creator_invite_name_required'), 422);
            }
        }

        $pendingExists = CreatorManagementInvite::query()
            ->where('management_id', $management->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', ManagementInviteStatus::Pending->value)
            ->exists();
        if ($pendingExists) {
            throw new VtualException(__('api/vtual.management.invite_already_pending'), 422);
        }

        /** @var CreatorManagementInvite $invite */
        $invite = CreatorManagementInvite::query()->create([
            'management_id' => $management->id,
            'email' => $email,
            'user_id' => $invitee->id,
            'mode' => $mode,
            'creator_id' => $creatorId,
            'new_display_name' => $newDisplayName,
            'token' => Str::random(48),
            'status' => ManagementInviteStatus::Pending,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        $invite->setRelation('management', $management);
        $invitee->notify(new ManagementCreatorInvited($invite));

        $this->notifications->sendToUsers(
            NotificationType::VtualManagementCreatorInviteReceived,
            [$invitee->id],
            [
                'management' => $management->name,
                'inviter' => $actor->name,
            ],
            'vtual.invites.respond',
            ['token' => $invite->token, 'kind' => 'creator'],
        );

        $this->realtime->changed($management->id, 'creator_invite', (string) $invite->id, 'created');

        return $invite->load('inviter');
    }

    /**
     * @return Collection<int, CreatorManagementInvite>
     */
    public function listInvites(User $actor, string $managementId): Collection
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanView($management, $actor);

        return CreatorManagementInvite::query()
            ->where('management_id', $management->id)
            ->with(['inviter', 'creator'])
            ->latest()
            ->get();
    }

    /**
     * Cancel a pending creator invite.
     */
    public function cancelInvite(User $actor, string $managementId, string $inviteId): CreatorManagementInvite
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        /** @var CreatorManagementInvite|null $invite */
        $invite = CreatorManagementInvite::query()
            ->where('management_id', $management->id)
            ->whereKey($inviteId)
            ->first();

        if ($invite === null) {
            throw new VtualException(__('api/vtual.management.invite_expired'), 404);
        }

        if (! $invite->isPending()) {
            throw new VtualException(__('api/vtual.management.invite_not_actionable'), 422);
        }

        $invite->status = ManagementInviteStatus::Cancelled;
        $invite->responded_at = now();
        $invite->save();

        $this->realtime->changed($management->id, 'creator_invite', (string) $invite->id, 'cancelled');

        return $invite->load('inviter');
    }

    public function acceptInvite(User $actor, string $token): CreatorManagementInvite
    {
        $invite = $this->findActionableInviteOrFail($actor, $token);

        $accepted = DB::transaction(function () use ($invite, $actor): CreatorManagementInvite {
            $managementId = (string) $invite->management_id;
            $creatorId = (string) $invite->creator_id;

            if ($invite->mode === 'existing') {
                // Invitee must still own the profile at accept time.
                $owns = CreatorMember::query()
                    ->where('creator_id', $creatorId)
                    ->where('user_id', $actor->id)
                    ->where('role', CreatorMemberRole::Owner)
                    ->exists();
                if (! $owns) {
                    throw new VtualException(__('api/vtual.management.creator_invite_not_owner'), 422);
                }
            } else {
                // Mint an orphan creator + Owner membership for the invitee.
                $creator = $this->createNewCreatorFor($actor, (string) $invite->new_display_name);
                $creatorId = (string) $creator->id;
            }

            ManagementCreator::query()->updateOrCreate(
                [
                    'management_id' => $managementId,
                    'creator_id' => $creatorId,
                ],
                [
                    'joined_at' => now(),
                    'left_at' => null,
                ],
            );

            $invite->creator_id = $creatorId;
            $invite->status = ManagementInviteStatus::Accepted;
            $invite->responded_at = now();
            $invite->save();

            return $invite;
        });

        $this->realtime->changed((string) $accepted->management_id, 'creator_invite', (string) $accepted->id, 'accepted');
        $this->menusForget((string) $actor->id);

        return $accepted->load('management', 'creator');
    }

    public function declineInvite(User $actor, string $token): CreatorManagementInvite
    {
        $invite = $this->findActionableInviteOrFail($actor, $token);

        $invite->status = ManagementInviteStatus::Declined;
        $invite->responded_at = now();
        $invite->save();

        $this->realtime->changed((string) $invite->management_id, 'creator_invite', (string) $invite->id, 'declined');

        return $invite->load('management');
    }

    /**
     * Prefill for the SPA respond screen — same guards as accept/decline.
     */
    public function showInviteByToken(User $actor, string $token): CreatorManagementInvite
    {
        $invite = $this->findActionableInviteOrFail($actor, $token);

        return $invite->load('management', 'inviter', 'creator');
    }

    /**
     * Resolve invite by token with pending/expiry + account-match guards.
     */
    private function findActionableInviteOrFail(User $actor, string $token): CreatorManagementInvite
    {
        /** @var CreatorManagementInvite|null $invite */
        $invite = CreatorManagementInvite::query()->where('token', $token)->first();
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

    private function createNewCreatorFor(User $invitee, string $displayName): Creator
    {
        $name = trim($displayName);
        if ($name === '') {
            throw new VtualException(__('api/vtual.creator.name_required'), 422);
        }

        $slug = SlugGenerator::unique($name, fn (string $s): bool => Creator::query()
            ->where('slug', $s)
            ->exists());

        $creator = Creator::query()->create([
            'slug' => $slug,
            'display_name' => $name,
            'status' => EntityStatus::Active,
        ]);

        CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $invitee->id,
            'role' => CreatorMemberRole::Owner,
        ]);

        $roleName = (string) setting_config('vtual.claim.vtuber_role', 'Vtual vTuber');
        if (! $invitee->hasRole($roleName)) {
            $invitee->assignRole($roleName);
            if (app()->bound(MenuRepositoryInterface::class)) {
                app(MenuRepositoryInterface::class)->forgetPresentedTreeForUserId((string) $invitee->id);
            }
        }

        $this->activations->activateImplicit($invitee, 'Vtual', 'management_creator_invite');

        return $creator;
    }

    /**
     * Creator assignment affects the presented menu (rule 7) — refresh the
     * viewer's presented tree + fire the targeted navigation refresh.
     */
    private function menusForget(string $userId): void
    {
        if (app()->bound(MenuRepositoryInterface::class)) {
            app(MenuRepositoryInterface::class)->forgetPresentedTreeForUserId($userId);
        }
        $this->navigationRealtime->userTreeChanged($userId);
    }
}
