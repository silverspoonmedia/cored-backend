<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class OrphanCreatorApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $creators;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
    }

    #[Test]
    public function orphans_index_excludes_owned_creators(): void
    {
        $admin = $this->userWithRole('Admin');
        app(ProductActivationService::class)->activateImplicit($admin, 'Vtual');

        $orphan = $this->creators->createOrphan($admin, ['display_name' => 'Orphan A']);
        $owned = $this->creators->create($this->userWithRole('User'), ['display_name' => 'Owned B']);

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/creators/orphans')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains((string) $orphan->id, $ids);
        $this->assertNotContains((string) $owned->id, $ids);
    }

    #[Test]
    public function orphans_datatable_returns_actions_column(): void
    {
        $admin = $this->userWithRole('Admin');
        app(ProductActivationService::class)->activateImplicit($admin, 'Vtual');

        $this->creators->createOrphan($admin, ['display_name' => 'Orphan DT']);

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/creators/orphans?display_mode=datatable&draw=1&start=0&length=50')
            ->assertOk();

        $row = $response->json('data.0');
        $this->assertNotNull($row);
        $this->assertArrayHasKey('actions', $row);
        $this->assertNotEmpty($row['actions']);
    }

    #[Test]
    public function orphan_show_rejects_claimed_profile(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User');
        app(ProductActivationService::class)->activateImplicit($admin, 'Vtual');

        $owned = $this->creators->create($user, ['display_name' => 'Now Owned']);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/creators/orphans/'.$owned->id)
            ->assertForbidden();
    }

    #[Test]
    public function orphan_show_returns_claimable_profile(): void
    {
        $admin = $this->userWithRole('Admin');
        app(ProductActivationService::class)->activateImplicit($admin, 'Vtual');

        $orphan = $this->creators->createOrphan($admin, ['display_name' => 'Show Me']);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/creators/orphans/'.$orphan->id)
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Show Me');
    }

    #[Test]
    public function create_orphan_auto_slug_has_six_char_prefix(): void
    {
        $admin = $this->userWithRole('Admin');
        app(ProductActivationService::class)->activateImplicit($admin, 'Vtual');

        $orphan = $this->creators->createOrphan($admin, ['display_name' => 'Ginjo Saine']);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{6}-ginjo-saine$/', $orphan->slug);
    }

    #[Test]
    public function two_orphans_with_same_display_name_get_distinct_prefixed_slugs(): void
    {
        $admin = $this->userWithRole('Admin');

        $first = $this->creators->createOrphan($admin, ['display_name' => 'Ginjo Saine']);
        $second = $this->creators->createOrphan($admin, ['display_name' => 'Ginjo Saine']);

        $this->assertNotSame($first->slug, $second->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6}-ginjo-saine$/', $first->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6}-ginjo-saine$/', $second->slug);
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

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
