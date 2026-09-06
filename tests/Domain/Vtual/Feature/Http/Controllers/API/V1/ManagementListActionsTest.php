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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * List row actions are membership-aware and the archive (destroy) endpoint is
 * reserved to org Owner/Admin via real vtual_management_members rows:
 * - every member (Owner/Admin/Staff) gets the Edit nav action;
 * - only Owner/Admin members get the Archive mutate action;
 * - internal staff (Root/Admin) WITHOUT membership get Edit only;
 * - DELETE managements/{id} enforces the same split server-side.
 */
class ManagementListActionsTest extends TestCase
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

    private function addMember(Management $management, User $user, ManagementMemberRole $role): void
    {
        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstRowActions(User $viewer): ?array
    {
        $rows = $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/managements')
            ->assertOk()
            ->json('data');

        return $rows[0]['actions'] ?? null;
    }

    private static function actionKeys(?array $actions): array
    {
        return array_map(fn (array $action): string => (string) ($action['key'] ?? ''), $actions ?? []);
    }

    #[Test]
    public function owner_member_gets_edit_and_archive_actions(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $this->makeManagement($owner);

        $keys = $this->actionKeys($this->firstRowActions($owner));

        $this->assertContains('edit', $keys);
        $this->assertContains('archive', $keys);
    }

    #[Test]
    public function admin_member_gets_edit_and_archive_actions(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $admin = $this->userWithRole('User');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->addMember($management, $admin, ManagementMemberRole::Admin);

        $keys = $this->actionKeys($this->firstRowActions($admin));

        $this->assertContains('edit', $keys);
        $this->assertContains('archive', $keys);
    }

    #[Test]
    public function staff_member_gets_edit_only(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $staff = $this->userWithRole('User');
        $this->activations->activateImplicit($staff, 'Vtual');
        $this->addMember($management, $staff, ManagementMemberRole::Staff);

        $keys = $this->actionKeys($this->firstRowActions($staff));

        $this->assertContains('edit', $keys);
        $this->assertNotContains('archive', $keys);
    }

    #[Test]
    public function internal_admin_without_membership_gets_edit_only(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $this->makeManagement($owner);

        $admin = $this->userWithRole('Admin');

        $keys = $this->actionKeys($this->firstRowActions($admin));

        $this->assertContains('edit', $keys);
        $this->assertNotContains('archive', $keys);
    }

    #[Test]
    public function plain_user_without_any_relation_gets_no_actions(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $this->makeManagement($owner);

        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $keys = $this->actionKeys($this->firstRowActions($outsider));

        $this->assertSame([], $keys);
    }

    #[Test]
    public function owner_can_archive_management(): void
    {
        Event::fake([\App\Domain\Vtual\Events\ManagementUpdated::class]);

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id)
            ->assertOk();

        $this->assertSoftDeleted('vtual_managements', ['id' => $management->id]);
        $this->assertDatabaseMissing('vtual_managements', ['id' => $management->id, 'deleted_at' => null]);

        // Soft-deleted orgs no longer appear in the index.
        $rows = $this->actingAsJwt($owner->fresh())
            ->getJson('/api/v1/vtual/managements')
            ->assertOk()
            ->json('data');
        $this->assertSame([], $rows);
    }

    #[Test]
    public function staff_member_cannot_archive_management(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $staff = $this->userWithRole('User');
        $this->activations->activateImplicit($staff, 'Vtual');
        $this->addMember($management, $staff, ManagementMemberRole::Staff);

        $this->actingAsJwt($staff)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id)
            ->assertForbidden();

        $this->assertDatabaseHas('vtual_managements', ['id' => $management->id, 'deleted_at' => null]);
    }

    #[Test]
    public function internal_admin_without_membership_cannot_archive_management(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id)
            ->assertForbidden();

        $this->assertDatabaseHas('vtual_managements', ['id' => $management->id, 'deleted_at' => null]);
    }

    #[Test]
    public function outsider_without_membership_cannot_archive_management(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $this->actingAsJwt($outsider)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id)
            ->assertForbidden();

        $this->assertDatabaseHas('vtual_managements', ['id' => $management->id, 'deleted_at' => null]);
    }
}
