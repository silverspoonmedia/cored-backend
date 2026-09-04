<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Notification\Notifications\NotificationType;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementInvite;
use App\Domain\Vtual\Models\ManagementMember;
use App\Domain\Vtual\Notifications\ManagementMemberInvited;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ManagementInvitesApiTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
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
            'status' => \App\Domain\Vtual\Enums\EntityStatus::Active,
        ]);

        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $owner->id,
            'role' => ManagementMemberRole::Owner,
        ]);

        return $management;
    }

    /**
     * @return array{0: User, 1: Management}
     */
    private function ownerWithManagement(): array
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');

        return [$owner, $this->makeManagement($owner)];
    }

    #[Test]
    public function owner_can_invite_member_and_notifications_fire(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $invite = $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('pending', $invite['status']);
        $this->assertSame($invitee->email, $invite['email']);
        $this->assertArrayNotHasKey('token', $invite);

        $this->assertDatabaseHas('vtual_management_invites', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
            'status' => 'pending',
        ]);

        Notification::assertSentTo($invitee, ManagementMemberInvited::class);

        $this->assertDatabaseHas('activity_notifications', [
            'type' => NotificationType::VtualManagementInviteReceived->value,
            'action_route' => 'vtual.invites.respond',
        ]);
    }

    #[Test]
    public function duplicate_pending_invite_is_rejected(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function invite_to_unregistered_email_is_rejected(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => 'ghost-'.Str::random(6).'@example.com',
                'role' => 'staff',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function owner_cannot_invite_self(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $owner->email,
                'role' => 'admin',
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function existing_member_cannot_be_invited(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();

        $member = $this->userWithRole('User');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $member->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $member->email,
                'role' => 'admin',
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function accept_creates_membership(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($invitee)
            ->getJson('/api/v1/vtual/invites/respond/'.$invite->token)
            ->assertOk()
            ->assertJsonPath('data.management.id', $management->id)
            ->assertJsonPath('data.role', 'staff');

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('vtual_management_members', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
            'role' => 'staff',
        ]);
    }

    #[Test]
    public function decline_marks_invite_declined_without_membership(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/decline')
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseMissing('vtual_management_members', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
        ]);
    }

    #[Test]
    public function cancelled_invite_cannot_be_accepted(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/invites/'.$invite->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertUnprocessable();
    }

    #[Test]
    public function cancelling_accepted_invite_revokes_membership(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'admin',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertOk();

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/invites/'.$invite->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseMissing('vtual_management_members', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
        ]);
    }

    #[Test]
    public function wrong_account_cannot_accept(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $other = $this->userWithRole('User');
        $this->activations->activateImplicit($other, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($other)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertForbidden();
    }

    #[Test]
    public function expired_invite_cannot_be_accepted(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();
        $invite->expires_at = now()->subDay();
        $invite->save();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertUnprocessable();
    }

    #[Test]
    public function staff_member_cannot_invite_or_cancel(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();

        $staff = $this->userWithRole('User');
        $this->activations->activateImplicit($staff, 'Vtual');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $staff->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $invitee = $this->userWithRole('User');

        $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertForbidden();

        // Owner creates a pending invite; staff still cannot cancel it.
        $invite = ManagementInvite::query()->create([
            'management_id' => $management->id,
            'email' => $invitee->email,
            'user_id' => $invitee->id,
            'role' => ManagementMemberRole::Staff,
            'token' => Str::random(48),
            'status' => ManagementInviteStatus::Pending,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAsJwt($staff)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/invites/'.$invite->id)
            ->assertForbidden();
    }

    #[Test]
    public function invites_list_shows_invites_without_tokens(): void
    {
        Notification::fake();

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $list = $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/managements/'.$management->id.'/invites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        $this->assertSame($invitee->email, $list[0]['email']);
        $this->assertSame('pending', $list[0]['status']);
        $this->assertArrayNotHasKey('token', $list[0]);
    }

    #[Test]
    public function unknown_token_returns_not_found(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/invites/respond/'.Str::random(48))
            ->assertNotFound();
    }
}
