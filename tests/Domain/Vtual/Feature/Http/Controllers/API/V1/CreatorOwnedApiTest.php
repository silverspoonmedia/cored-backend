<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use App\Support\SpaPublicUrl;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorOwnedApiTest extends TestCase
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
    public function owned_creators_returns_only_profiles_user_owns(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Vtual vTuber');
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $owned = $this->creators->create($user, ['display_name' => 'Owned One']);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators/owned')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $owned->id)
            ->assertJsonPath('data.0.display_name', 'Owned One')
            ->assertJsonPath('data.0.profile_url', SpaPublicUrl::vtuberProfile($owned->slug));

        $actions = collect($this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators/owned')
            ->json('data.0.actions'));
        $this->assertSame(['edit', 'view'], $actions->pluck('key')->all());
        $this->assertSame('vtual.profiles.show', $actions->firstWhere('key', 'edit')['route']);
        $this->assertSame(SpaPublicUrl::vtuberProfile($owned->slug), $actions->firstWhere('key', 'view')['href']);
        $this->assertSame('_blank', $actions->firstWhere('key', 'view')['target']);
    }

    #[Test]
    public function owned_creators_list_batches_membership_lookups(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Vtual vTuber');
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $this->creators->create($user, ['display_name' => 'Owned Alpha']);
        $this->creators->create($user, ['display_name' => 'Owned Beta']);
        $this->creators->create($user, ['display_name' => 'Owned Gamma']);

        $memberQueries = 0;
        DB::listen(function ($query) use (&$memberQueries): void {
            if (str_contains((string) $query->sql, 'vtual_creator_members')) {
                $memberQueries++;
            }
        });

        $rows = $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators/owned')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $rows);
        $this->assertSame(['owner', 'owner', 'owner'], collect($rows)->pluck('my_role')->all());
        $this->assertLessThanOrEqual(2, $memberQueries);
    }

    #[Test]
    public function owned_creators_empty_when_user_has_no_owned_profiles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Vtual Viewer');
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators/owned')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
