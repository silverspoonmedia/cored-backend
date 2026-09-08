<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\ManagementMember;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Management authority over linked vTuber profiles:
 * Owner/Admin may PATCH, Staff is view-only, non-members denied — all
 * without any Spatie vtual-creator permission on the member accounts.
 */
class ManagementCreatorProfileAccessTest extends TestCase
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

    /**
     * Management with the given member + an active assignment to a creator
     * owned by an unrelated vTuber user. None of the management members get
     * any Spatie vtual permission beyond the seeded role.
     */
    protected function linkedScenario(ManagementMemberRole $memberRole): array    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');

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

        $member = $this->userWithRole('User');
        $this->activations->activateImplicit($member, 'Vtual');
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $member->id,
            'role' => $memberRole,
        ]);

        // Linked creator, owned by an unrelated external vTuber user.
        $vtuber = $this->userWithRole('Vtual vTuber');
        $creator = Creator::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'creator-'.Str::random(8),
            'display_name' => 'Linked Creator',
            'status' => \App\Domain\Vtual\Enums\EntityStatus::Active,
        ]);
        \App\Domain\Vtual\Models\CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $vtuber->id,
            'role' => \App\Domain\Vtual\Enums\CreatorMemberRole::Owner,
        ]);

        ManagementCreator::query()->create([
            'management_id' => $management->id,
            'creator_id' => $creator->id,
            'joined_at' => now(),
        ]);

        return [$management, $creator, $owner, $member, $vtuber];
    }

    #[Test]
    public function management_owner_can_patch_linked_creator_profile(): void
    {
        [$management, $creator, $owner] = $this->linkedScenario(ManagementMemberRole::Owner);

        $this->actingAsJwt($owner)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, [
                'bio' => 'Updated by management owner',
            ])
            ->assertOk();
    }

    #[Test]
    public function management_admin_can_patch_linked_creator_profile(): void
    {
        [$management, $creator, $owner, $admin] = $this->linkedScenario(ManagementMemberRole::Admin);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, [
                'bio' => 'Updated by management admin',
            ])
            ->assertOk();
    }

    #[Test]
    public function management_staff_can_view_but_not_patch(): void
    {
        [$management, $creator, $owner, $staff] = $this->linkedScenario(ManagementMemberRole::Staff);

        $this->actingAsJwt($staff)
            ->getJson('/api/v1/vtual/creators/'.$creator->id)
            ->assertOk()
            ->assertJsonPath('data.my_management_role', 'staff');
        $this->actingAsJwt($staff)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, [
                'bio' => 'Staff must not write',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function non_member_without_permissions_is_denied(): void
    {
        [$management, $creator] = $this->linkedScenario(ManagementMemberRole::Owner);

        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $this->actingAsJwt($outsider)
            ->getJson('/api/v1/vtual/creators/'.$creator->id)
            ->assertForbidden();

        $this->actingAsJwt($outsider)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, [
                'bio' => 'Nope',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function my_management_role_exposed_for_owner(): void
    {
        [$management, $creator, $owner] = $this->linkedScenario(ManagementMemberRole::Owner);

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/creators/'.$creator->id)
            ->assertOk()
            ->assertJsonPath('data.my_management_role', 'owner');
    }

    #[Test]
    public function staff_cannot_assign_creators_to_management(): void
    {
        Event::fake();

        [$management, $creator, $owner, $staff, $vtuber] = $this->linkedScenario(ManagementMemberRole::Staff);

        $otherCreator = Creator::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'other-'.Str::random(8),
            'display_name' => 'Other Creator',
            'status' => \App\Domain\Vtual\Enums\EntityStatus::Active,
        ]);

        $this->actingAsJwt($staff)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creators', [
                'creator_id' => $otherCreator->id,
            ])
            ->assertForbidden();
    }
}
