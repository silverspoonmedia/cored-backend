<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Notification\Models\ActivityNotification;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\CreatorManagementInvite;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\ManagementMember;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Invite-vTuber flow: mode existing/new, guards (ownership, duplication,
 * email-match), cancel, and access to linked profiles via management role.
 */
class ManagementCreatorInvitesApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeManagement(User $owner): Management
    {
        $management = Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'org-'.Str::random(8),
            'name' => 'Org '.Str::random(6),
            'status' => EntityStatus::Active,
        ]);

        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $owner->id,
            'role' => ManagementMemberRole::Owner,
        ]);

        return $management;
    }

    private function makeOwnedCreator(User $owner, string $name = 'Creator'): Creator
    {
        $creator = Creator::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => Str::slug($name.'-'.Str::random(6)),
            'display_name' => $name,
            'status' => EntityStatus::Active,
        ]);

        CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $owner->id,
            'role' => CreatorMemberRole::Owner,
        ]);

        return $creator;
    }

    #[Test]
    public function owner_can_invite_existing_creator_of_invitee(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $creator = $this->makeOwnedCreator($invitee, 'Existing Talent');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'existing',
                'creator_id' => $creator->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'existing')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('vtual_creator_management_invites', [
            'management_id' => $management->id,
            'email' => $invitee->email,
            'mode' => 'existing',
            'creator_id' => $creator->id,
            'status' => 'pending',
        ]);

        // Activity notification with kind=creator deep link.
        $this->assertDatabaseHas('activity_notifications', [
            'type' => 'vtual_management_creator_invite_received',
        ]);
    }

    #[Test]
    public function invite_existing_requires_invitee_to_own_profile(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        // Profile owned by someone else.
        $other = $this->userWithRole('User');
        $creator = $this->makeOwnedCreator($other, 'Not Yours');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'existing',
                'creator_id' => $creator->id,
            ])
            ->assertStatus(422);

        // Non-existent profile id → 404.
        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'existing',
                'creator_id' => (string) Str::uuid7(),
            ])
            ->assertStatus(404);
    }

    #[Test]
    public function duplicate_pending_invite_is_rejected(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $creator = $this->makeOwnedCreator($invitee, 'Talent');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'existing',
                'creator_id' => $creator->id,
            ])
            ->assertCreated();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'existing',
                'creator_id' => $creator->id,
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function accepting_existing_invite_links_profile_to_management(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $this->activations->activateImplicit($invitee, 'Vtual');
        $creator = $this->makeOwnedCreator($invitee, 'Link Me');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'existing',
                'creator_id' => $creator->id,
            ])
            ->assertCreated();

        $invite = CreatorManagementInvite::query()
            ->where('management_id', $management->id)
            ->where('email', $invitee->email)
            ->firstOrFail();

        // Email mismatch → 403.
        $wrongUser = $this->userWithRole('User', 'wrong-'.uniqid('', true).'@example.com');
        $this->actingAsJwt($wrongUser)
            ->postJson('/api/v1/vtual/creator-invites/respond/'.$invite->token.'/accept')
            ->assertStatus(403);

        $this->actingAsJwt($invitee)
            ->getJson('/api/v1/vtual/creator-invites/respond/'.$invite->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/creator-invites/respond/'.$invite->token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('vtual_management_creators', [
            'management_id' => $management->id,
            'creator_id' => $creator->id,
            'left_at' => null,
        ]);

        $this->assertEquals(ManagementInviteStatus::Accepted, $invite->refresh()->status);
    }

    #[Test]
    public function accepting_new_invite_creates_orphan_profile_owned_by_invitee(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'new',
                'new_display_name' => 'Fresh Talent '.Str::random(4),
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'new');

        $invite = CreatorManagementInvite::query()
            ->where('management_id', $management->id)
            ->where('email', $invitee->email)
            ->firstOrFail();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/creator-invites/respond/'.$invite->token.'/accept')
            ->assertOk();

        $creatorId = $invite->refresh()->creator_id;
        $this->assertNotNull($creatorId);

        // Profile created + owned by invitee.
        $creator = Creator::query()->findOrFail($creatorId);
        $this->assertSame(EntityStatus::Active, $creator->status);
        $this->assertDatabaseHas('vtual_creator_members', [
            'creator_id' => $creatorId,
            'user_id' => $invitee->id,
            'role' => 'owner',
        ]);

        // Linked to the management.
        $this->assertDatabaseHas('vtual_management_creators', [
            'management_id' => $management->id,
            'creator_id' => $creatorId,
        ]);

        // Spatie vtuber role assigned.
        $vtuberRole = (string) setting_config('vtual.claim.vtuber_role', 'Vtual vTuber');
        $this->assertTrue($invitee->refresh()->hasRole($vtuberRole));
    }

    #[Test]
    public function decline_and_cancel_work(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $this->activations->activateImplicit($invitee, 'Vtual');

        // Decline path.
        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'new',
                'new_display_name' => 'Decline Me',
            ])
            ->assertCreated();

        $declineInvite = CreatorManagementInvite::query()
            ->where('email', $invitee->email)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/creator-invites/respond/'.$declineInvite->token.'/decline')
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        // Cancel path (after pending accepted-status reset needed — make a new one).
        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'new',
                'new_display_name' => 'Cancel Me',
            ])
            ->assertCreated();

        $cancelInvite = CreatorManagementInvite::query()
            ->where('email', $invitee->email)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites/'.$cancelInvite->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    #[Test]
    public function staff_cannot_send_creator_invites(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $staffUser = $this->userWithRole('User');
        $this->activations->activateImplicit($staffUser, 'Vtual');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $staffUser->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');

        $this->actingAsJwt($staffUser)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-invites', [
                'email' => $invitee->email,
                'mode' => 'new',
                'new_display_name' => 'Nope',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function candidates_endpoint_lists_only_invitee_owned_profiles(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $creator = $this->makeOwnedCreator($invitee, 'Candidate One');

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/managements/'.$management->id.'/creator-invite-candidates?email='.urlencode($invitee->email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $creator->id);

        // Unknown email → 404.
        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/managements/'.$management->id.'/creator-invite-candidates?email=nobody-'.uniqid('', true).'@example.com')
            ->assertStatus(404);
    }

    #[Test]
    public function management_owner_admin_can_edit_linked_profile_but_staff_cannot(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $invitee = $this->userWithRole('User', 'vtuber-'.uniqid('', true).'@example.com');
        $this->activations->activateImplicit($invitee, 'Vtual');
        $creator = $this->makeOwnedCreator($invitee, 'Editable Talent');

        ManagementCreator::query()->create([
            'id' => (string) Str::uuid7(),
            'management_id' => $management->id,
            'creator_id' => $creator->id,
            'joined_at' => now(),
        ]);

        // Org owner (non-creator-member) can update the profile.
        $this->actingAsJwt($owner)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, [
                'display_name' => 'Edited by Org Owner',
            ])
            ->assertOk();

        // Staff member can view but not update.
        $staffUser = $this->userWithRole('User');
        $this->activations->activateImplicit($staffUser, 'Vtual');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $staffUser->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $this->actingAsJwt($staffUser)
            ->getJson('/api/v1/vtual/creators/'.$creator->id)
            ->assertOk();

        $this->actingAsJwt($staffUser)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, [
                'display_name' => 'Edited by Staff',
            ])
            ->assertForbidden();
    }
}
