<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementMember;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ManagementMembersApiTest extends TestCase
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

    #[Test]
    public function owner_can_update_and_remove_member(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $target = $this->userWithRole('User');
        $member = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $target->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $this->actingAsJwt($owner)
            ->patchJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$member->id, [
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$member->id)
            ->assertOk();

        $this->assertDatabaseMissing('vtual_management_members', ['id' => $member->id]);
    }

    #[Test]
    public function staff_member_cannot_manage_members(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $staff = $this->userWithRole('User');
        $this->activations->activateImplicit($staff, 'Vtual');
        $staffMember = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $staff->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $target = $this->userWithRole('User');
        $targetMember = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $target->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $this->actingAsJwt($staff)
            ->patchJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$targetMember->id, [
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->actingAsJwt($staff)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$targetMember->id)
            ->assertForbidden();
    }

    #[Test]
    public function non_member_cannot_manage_members(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $ownerMember = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->actingAsJwt($outsider)
            ->patchJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$ownerMember->id, [
                'role' => 'admin',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function owner_role_is_not_assignable_via_endpoint(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $target = $this->userWithRole('User');
        $member = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $target->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $this->actingAsJwt($owner)
            ->patchJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$member->id, [
                'role' => 'owner',
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function actor_cannot_edit_own_membership(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $other = $this->userWithRole('User');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $other->id,
            'role' => ManagementMemberRole::Owner,
        ]);

        $admin = $this->userWithRole('User');
        $this->activations->activateImplicit($admin, 'Vtual');
        $adminMember = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $admin->id,
            'role' => ManagementMemberRole::Admin,
        ]);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$adminMember->id, [
                'role' => 'staff',
            ])
            ->assertUnprocessable();

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$adminMember->id)
            ->assertUnprocessable();
    }

    #[Test]
    public function last_owner_cannot_be_demoted_or_removed(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $admin = $this->userWithRole('User');
        $this->activations->activateImplicit($admin, 'Vtual');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $admin->id,
            'role' => ManagementMemberRole::Admin,
        ]);

        $ownerMember = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$ownerMember->id, [
                'role' => 'staff',
            ])
            ->assertUnprocessable();

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/members/'.$ownerMember->id)
            ->assertUnprocessable();
    }

    #[Test]
    public function members_list_includes_user_details(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/managements/'.$management->id.'/members')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $owner->id)
            ->assertJsonPath('data.0.role', 'owner');
    }
}
