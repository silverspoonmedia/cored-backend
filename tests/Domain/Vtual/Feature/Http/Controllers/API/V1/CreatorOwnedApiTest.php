<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('data.0.profile_url', route('vtual.vtuber.show', $owned->slug));
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
