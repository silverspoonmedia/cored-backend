<?php

namespace Tests\Domain\Navigation\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Repositories\Eloquent\MenuRepository;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementMember;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * vTual management membership visibility (rule 7) with the dual placement:
 *
 * - Products → vTual → Managements carries EMPTY stored roles and is granted
 *   exclusively through a vtual_management_members row (member-only surface).
 * - Administration → vTual → Managements is Root/Admin (staff manage orgs;
 *   members never see it because the Administration parent's route is not in
 *   MANAGEMENT_MEMBER_ROUTES, so rule 7 fails on the ancestor chain).
 *
 * An admin who is ALSO an org member sees both entries. Helpers locate nodes
 * by tree structure, not flat names — both branches carry a "Managements".
 */
class ManagementMemberMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VtualRoleSeeder::class);
        $this->seed(\Database\Seeders\DomainAccessSeeder::class);

        $repository = app(MenuRepository::class);

        // Products branch: header → parent mirrors the seeded Vtual product
        // roles; the Managements child is member-only (empty roles + rule 7).
        $productsHeader = $repository->createMenu([
            'name' => 'Products',
            'type' => 'h',
        ], 'append', null);
        $productsParent = $repository->createMenu([
            'name' => 'vTual',
            'type' => 'p',
            'route' => 'vtual.profiles.index',
            'parent_id' => $productsHeader->id,
        ], 'append', null);
        $repository->syncRoles($productsParent, ['Vtual vTuber', 'Vtual Editor', 'Vtual Admin']);

        $repository->createMenu([
            'name' => 'My vTuber profiles',
            'type' => 'c',
            'route' => 'vtual.profiles.index',
            'parent_id' => $productsParent->id,
        ], 'append', null);

        $productsManagements = $repository->createMenu([
            'name' => 'Managements',
            'type' => 'c',
            'route' => 'vtual.managements.index',
            'parent_id' => $productsParent->id,
        ], 'append', null);
        $repository->syncRoles($productsManagements, []);

        $productsEvents = $repository->createMenu([
            'name' => 'Events',
            'type' => 'c',
            'route' => 'vtual.events.index',
            'parent_id' => $productsParent->id,
        ], 'append', null);
        $repository->syncRoles($productsEvents, []);

        // Administration branch: staff-only, untouched by rule 7.
        $adminHeader = $repository->createMenu([
            'name' => 'Administration',
            'type' => 'h',
        ], 'append', null);
        $adminParent = $repository->createMenu([
            'name' => 'vTual',
            'type' => 'p',
            'route' => 'vtual.orphans.index',
            'parent_id' => $adminHeader->id,
        ], 'append', null);
        $repository->syncRoles($adminParent, ['Root', 'Admin', 'Moderator']);

        $repository->createMenu([
            'name' => 'Orphan vTubers',
            'type' => 'c',
            'route' => 'vtual.orphans.index',
            'parent_id' => $adminParent->id,
        ], 'append', null);

        $adminManagements = $repository->createMenu([
            'name' => 'Managements',
            'type' => 'c',
            'route' => 'vtual.managements.index',
            'parent_id' => $adminParent->id,
        ], 'append', null);
        $repository->syncRoles($adminManagements, ['Root', 'Admin']);
    }

    public function test_member_without_staff_role_sees_products_managements(): void
    {
        $user = $this->userWithMembership();

        $this->assertTrue($this->hasProductsManagements($user));
        $this->assertFalse($this->hasAdministrationManagements($user));
    }

    public function test_plain_user_without_membership_does_not_see_management_menu(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $names = $this->treeNames($user->fresh());

        $this->assertNotContains('Managements', $names);
        $this->assertFalse($this->hasProductsManagements($user->fresh()));
        $this->assertFalse($this->hasAdministrationManagements($user->fresh()));
    }

    public function test_admin_without_membership_sees_only_administration_managements(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        app(ProductActivationService::class)->activateImplicit($admin, 'Vtual');

        $admin = $admin->fresh();

        $this->assertTrue($this->hasAdministrationManagements($admin));
        $this->assertFalse($this->hasProductsManagements($admin));
    }

    public function test_admin_with_membership_sees_both_managements_entries(): void
    {
        $admin = $this->userWithMembership('Admin');

        $this->assertTrue($this->hasAdministrationManagements($admin));
        $this->assertTrue($this->hasProductsManagements($admin));
    }

    public function test_membership_removal_hides_products_managements(): void
    {
        $user = $this->userWithMembership();

        $this->assertTrue($this->hasProductsManagements($user));

        ManagementMember::query()->where('user_id', $user->id)->delete();
        app(MenuRepository::class)->forgetPresentedTreeForUserId((string) $user->id);

        $this->assertFalse($this->hasProductsManagements($user->fresh()));
    }

    public function test_member_sees_products_events_and_plain_user_does_not(): void
    {
        $member = $this->userWithMembership();

        $this->assertTrue($this->hasProductsEvents($member));

        $plain = User::factory()->create();
        $plain->assignRole('User');
        app(ProductActivationService::class)->activateImplicit($plain, 'Vtual');

        $this->assertFalse($this->hasProductsEvents($plain->fresh()));
    }

    private function userWithMembership(string $role = 'User'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $management = Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'org-'.Str::random(8),
            'name' => 'Org '.Str::random(6),
            'status' => EntityStatus::Active,
        ]);

        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $user->id,
            'role' => ManagementMemberRole::Staff,
        ]);

        return $user->fresh();
    }

    /**
     * True when the viewer sees the member-only Products "Managements" node
     * (Products → vTual → Managements), identified by structure, not the
     * flat name — both branches carry a node named "Managements".
     */
    private function hasProductsManagements(User $user): bool
    {
        foreach ($this->tree($user) as $group) {
            if (($group['name'] ?? null) !== 'Products') {
                continue;
            }

            foreach ($group['children'] as $product) {
                if (($product['name'] ?? null) !== 'vTual') {
                    continue;
                }

                foreach ($product['children'] as $child) {
                    if (($child['name'] ?? null) === 'Managements') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * True when the viewer sees the member-only Products "Events" node
     * (Products → vTual → Events, rule 7 membership grant).
     */
    private function hasProductsEvents(User $user): bool
    {
        foreach ($this->tree($user) as $group) {
            if (($group['name'] ?? null) !== 'Products') {
                continue;
            }

            foreach ($group['children'] as $product) {
                if (($product['name'] ?? null) !== 'vTual') {
                    continue;
                }

                foreach ($product['children'] as $child) {
                    if (($child['name'] ?? null) === 'Events') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * True when the viewer sees the staff "Managements" node under the
     * Administration vTual branch.
     */
    private function hasAdministrationManagements(User $user): bool
    {
        foreach ($this->tree($user) as $group) {
            if (($group['name'] ?? null) !== 'Administration') {
                continue;
            }

            foreach ($group['children'] as $parent) {
                if (($parent['name'] ?? null) !== 'vTual') {
                    continue;
                }

                foreach ($parent['children'] as $child) {
                    if (($child['name'] ?? null) === 'Managements') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function treeNames(User $user): array
    {
        $names = [];
        $walk = function (array $nodes) use (&$walk, &$names): void {
            foreach ($nodes as $node) {
                $names[] = $node['name'];
                $walk($node['children']);
            }
        };
        $walk($this->tree($user));

        return $names;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(User $user): array
    {
        return app(MenuRepository::class)->navigationTree($user, 'sidebar');
    }
}
