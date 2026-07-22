<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtualApiTest extends TestCase
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

    #[Test]
    public function user_can_create_and_list_creators(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $create = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/creators', [
                'display_name' => 'Neon Fox',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('neon-fox', $create['slug']);
        $this->assertSame('owner', $create['my_role']);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators')
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Neon Fox');
    }

    #[Test]
    public function suspended_activation_blocks_api(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->activations->activateImplicit($user, 'Vtual');
        $this->activations->suspend($activation);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators')
            ->assertForbidden();
    }

    #[Test]
    public function lazy_create_activation_on_first_hit(): void
    {
        $user = $this->userWithRole('User');

        $this->assertDatabaseMissing('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Vtual',
        ]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/platforms')
            ->assertOk();

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Vtual',
            'state' => ProductActivationState::Active->value,
            'source' => 'lazy_create',
        ]);
    }

    #[Test]
    public function channel_registration_and_management_assign(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $creator = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/creators', ['display_name' => 'Talent'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/creators/'.$creator['id'].'/channels', [
                'platform_key' => 'youtube',
                'external_id' => 'UCabc',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $management = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/managements', ['name' => 'Agency'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/managements/'.$management['id'].'/creators', [
                'creator_id' => $creator['id'],
            ])
            ->assertOk();

        $show = $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/managements/'.$management['id'])
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($show['creators'] ?? []);
    }

    #[Test]
    public function peer_cannot_view_others_creator_without_any_perm(): void
    {
        $owner = $this->userWithRole('User');
        $peer = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $this->activations->activateImplicit($peer, 'Vtual');

        $creator = $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/creators', ['display_name' => 'Private'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($peer)
            ->getJson('/api/v1/vtual/creators/'.$creator['id'])
            ->assertForbidden();
    }

    #[Test]
    public function platforms_include_disabled_kick(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/platforms')
            ->assertOk()
            ->json('data');

        $keys = collect($data)->pluck('key')->all();
        $this->assertContains('youtube', $keys);
        $this->assertContains('twitch', $keys);
        $this->assertContains('kick', $keys);

        $kick = collect($data)->firstWhere('key', 'kick');
        $this->assertFalse((bool) ($kick['enabled'] ?? true));
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
}
