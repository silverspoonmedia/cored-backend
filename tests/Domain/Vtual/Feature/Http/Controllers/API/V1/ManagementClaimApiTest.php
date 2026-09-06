<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementMember;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Management claim flow: staff seeds an orphan org (no Owner member),
 * then hands it over via an Owner-role invite accepted by the target email.
 */
class ManagementClaimApiTest extends TestCase
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

    #[Test]
    public function staff_can_create_orphan_management_without_owner(): void
    {
        $staff = $this->userWithRole('Admin');

        $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/orphan', [
                'name' => 'Claimable Org',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_claimable', true)
            ->assertJsonPath('data.my_role', 'owner'); // roleFor staff bypass

        $row = Management::query()->where('name', 'Claimable Org')->firstOrFail();
        $this->assertSame(0, ManagementMember::query()->where('management_id', $row->id)->count());
    }

    #[Test]
    public function plain_user_cannot_create_orphan_management(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/managements/orphan', [
                'name' => 'Nope Org',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('vtual_managements', ['name' => 'Nope Org']);
    }

    #[Test]
    public function staff_can_invite_owner_while_claimable(): void
    {
        Event::fake([
            \App\Domain\Vtual\Events\ManagementUpdated::class,
            \App\Domain\Navigation\Events\MenuTreeChanged::class,
        ]);
        Notification::fake();

        $staff = $this->userWithRole('Admin');
        $management = $this->createOrphanViaApi($staff);

        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'owner',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.status', 'pending');
    }

    #[Test]
    public function owner_invite_fails_when_management_already_has_owner(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = $this->createOrphanViaApi($staff);

        $owner = $this->userWithRole('User');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $owner->id,
            'role' => ManagementMemberRole::Owner,
        ]);

        $invitee = $this->userWithRole('User');

        $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'owner',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function member_admin_cannot_send_owner_invite(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = $this->createOrphanViaApi($staff);

        // An admin member on the (still ownerless) org.
        $admin = $this->userWithRole('User');
        $this->activations->activateImplicit($admin, 'Vtual');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $admin->id,
            'role' => ManagementMemberRole::Admin,
        ]);

        $invitee = $this->userWithRole('User');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'owner',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function accepting_owner_invite_creates_owner_membership(): void
    {
        Event::fake([
            \App\Domain\Vtual\Events\ManagementUpdated::class,
            \App\Domain\Navigation\Events\MenuTreeChanged::class,
        ]);
        Notification::fake();

        $staff = $this->userWithRole('Admin');
        $management = $this->createOrphanViaApi($staff);

        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'owner',
            ])
            ->assertCreated();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/invalid-token/accept')
            ->assertStatus(404);

        // Fetch the real token from the DB (respond flow is token-guarded).
        $token = \App\Domain\Vtual\Models\ManagementInvite::query()
            ->where('management_id', $management->id)
            ->where('email', $invitee->email)
            ->value('token');

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$token.'/accept')
            ->assertOk();

        $this->assertDatabaseHas('vtual_management_members', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
            'role' => 'owner',
        ]);
    }

    #[Test]
    public function staff_can_archive_orphan_management(): void
    {
        Event::fake([\App\Domain\Vtual\Events\ManagementUpdated::class]);

        $staff = $this->userWithRole('Admin');
        $management = $this->createOrphanViaApi($staff);

        $this->actingAsJwt($staff)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id)
            ->assertOk();

        $this->assertSoftDeleted('vtual_managements', ['id' => $management->id]);
    }

    #[Test]
    public function claimable_flag_is_false_once_owner_exists(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = $this->createOrphanViaApi($staff);

        $owner = $this->userWithRole('User');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $owner->id,
            'role' => ManagementMemberRole::Owner,
        ]);

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/managements/'.$management->id)
            ->assertOk()
            ->assertJsonPath('data.is_claimable', false);
    }

    private function createOrphanViaApi(User $staff): Management
    {
        $id = $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/orphan', [
                'name' => 'Claimable '.Str::random(6),
            ])
            ->assertCreated()
            ->json('data.id');

        return Management::query()->findOrFail($id);
    }
}
