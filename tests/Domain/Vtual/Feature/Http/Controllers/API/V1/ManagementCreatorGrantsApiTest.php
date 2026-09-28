<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\ManagementCreatorGrant;
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
 * Delegated per-creator capability grants
 * (docs/domains/vtual/reference-capability-grants.md).
 *
 * The model is **additive**: a grant is an extra allow on top of the org role,
 * so it can only ever widen access. These tests lock both halves of that
 * promise — a Staff member with a grant may edit the one creator named in the
 * row, and an Admin's org-wide power is unchanged by the matrix existing.
 */
class ManagementCreatorGrantsApiTest extends TestCase
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

    private function makeCreator(string $name = 'Linked Creator'): Creator
    {
        $creator = Creator::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'creator-'.Str::random(8),
            'display_name' => $name,
            'status' => EntityStatus::Active,
        ]);

        $vtuber = $this->userWithRole('Vtual vTuber');
        CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $vtuber->id,
            'role' => CreatorMemberRole::Owner,
        ]);

        return $creator;
    }

    /**
     * Org with an Owner, one extra member holding the given role, and one
     * actively assigned creator. No Spatie vtual permission on the members
     * beyond the plain `User` role.
     *
     * @return array{0: Management, 1: Creator, 2: User, 3: ManagementMember}
     */
    private function orgScenario(ManagementMemberRole $memberRole = ManagementMemberRole::Staff): array
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');

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

        $memberUser = $this->userWithRole('User');
        $this->activations->activateImplicit($memberUser, 'Vtual');
        $member = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $memberUser->id,
            'role' => $memberRole,
        ]);

        $creator = $this->makeCreator();
        ManagementCreator::query()->create([
            'management_id' => $management->id,
            'creator_id' => $creator->id,
            'joined_at' => now(),
        ]);

        return [$management, $creator, $owner, $member];
    }

    #[Test]
    public function owner_can_grant_and_revoke_a_capability(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $member->id,
                'creator_id' => $creator->id,
                'capability' => 'edit',
            ])
            ->assertCreated()
            ->assertJsonPath('data.capability', 'edit');

        $this->assertDatabaseHas('vtual_management_creator_grants', [
            'management_id' => $management->id,
            'member_id' => $member->id,
            'creator_id' => $creator->id,
            'capability' => 'edit',
        ]);

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/managements/'.$management->id)
            ->assertOk()
            ->assertJsonPath('data.creator_grants.0.capability', 'edit');

        $grantId = ManagementCreatorGrant::query()->firstOrFail()->id;

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants/'.$grantId)
            ->assertOk();

        $this->assertDatabaseMissing('vtual_management_creator_grants', ['id' => $grantId]);
    }

    #[Test]
    public function staff_member_with_edit_grant_can_patch_that_creator_only(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $otherCreator = $this->makeCreator('Other Creator');
        ManagementCreator::query()->create([
            'management_id' => $management->id,
            'creator_id' => $otherCreator->id,
            'joined_at' => now(),
        ]);

        // Baseline: Staff is view-only, so the PATCH is denied before the grant.
        $this->actingAsJwt($member->user)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, ['bio' => 'Before grant'])
            ->assertForbidden();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $member->id,
                'creator_id' => $creator->id,
                'capability' => 'edit',
            ])
            ->assertCreated();

        $this->actingAsJwt($member->user)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, ['bio' => 'Granted edit'])
            ->assertOk();

        // The grant is per creator: the second linked creator stays untouchable.
        $this->actingAsJwt($member->user)
            ->patchJson('/api/v1/vtual/creators/'.$otherCreator->id, ['bio' => 'Not granted'])
            ->assertForbidden();
    }

    #[Test]
    public function view_grant_reads_but_does_not_write(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $member->id,
                'creator_id' => $creator->id,
                'capability' => 'view',
            ])
            ->assertCreated();

        $this->actingAsJwt($member->user)
            ->getJson('/api/v1/vtual/creators/'.$creator->id)
            ->assertOk()
            ->assertJsonPath('data.my_grant_capability', 'view');

        $this->actingAsJwt($member->user)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, ['bio' => 'Nope'])
            ->assertForbidden();
    }

    #[Test]
    public function additive_model_leaves_the_admin_role_untouched(): void
    {
        [$management, $creator, $owner, $admin] = $this->orgScenario(ManagementMemberRole::Admin);

        // No grant exists anywhere; the Admin edits every linked creator anyway.
        $this->assertSame(0, ManagementCreatorGrant::query()->count());

        $this->actingAsJwt($admin->user)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, ['bio' => 'Admin, no grant'])
            ->assertOk();
    }

    #[Test]
    public function grant_is_inert_once_the_creator_is_unassigned(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $member->id,
                'creator_id' => $creator->id,
                'capability' => 'edit',
            ])
            ->assertCreated();

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/creators/'.$creator->id)
            ->assertOk();

        // Unassigning drops the delegation row, so a later re-assign cannot
        // silently resurrect an old grant.
        $this->assertDatabaseMissing('vtual_management_creator_grants', [
            'member_id' => $member->id,
            'creator_id' => $creator->id,
        ]);

        $this->actingAsJwt($member->user)
            ->patchJson('/api/v1/vtual/creators/'.$creator->id, ['bio' => 'Stale grant'])
            ->assertForbidden();
    }

    #[Test]
    public function grant_requires_an_active_assignment(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $unlinked = $this->makeCreator('Unlinked Creator');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $member->id,
                'creator_id' => $unlinked->id,
                'capability' => 'edit',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function grant_rejects_an_unknown_member_and_self_grant(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $outsider = $this->userWithRole('User');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => (string) Str::uuid7(),
                'creator_id' => $creator->id,
                'capability' => 'edit',
            ])
            ->assertStatus(422);

        $ownerMember = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $ownerMember->id,
                'creator_id' => $creator->id,
                'capability' => 'edit',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('vtual_management_creator_grants', 0);
        $this->assertNotNull($outsider);
    }

    #[Test]
    public function plain_member_cannot_grant(): void
    {
        [$management, $creator, $owner, $member] = $this->orgScenario();

        $otherMember = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $this->userWithRole('User')->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        $this->actingAsJwt($member->user)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/creator-grants', [
                'member_id' => $otherMember->id,
                'creator_id' => $creator->id,
                'capability' => 'edit',
            ])
            ->assertForbidden();
    }
}
