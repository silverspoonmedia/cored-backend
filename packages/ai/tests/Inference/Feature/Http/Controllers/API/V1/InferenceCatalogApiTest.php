<?php

namespace Tests\Domain\Inference\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InferenceCatalogApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(InferenceCatalogSeeder::class);
    }

    public function test_admin_can_crud_model_card(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/inference/model-cards', [
                'slug' => 'test-model-v1',
                'display_name' => 'Test Model',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'test-model-v1');

        $cardId = AiModelCard::query()->where('slug', 'test-model-v1')->value('id');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/inference/model-cards/'.$cardId)
            ->assertOk()
            ->assertJsonPath('data.slug', 'test-model-v1')
            ->assertJsonStructure(['data' => ['provider_rate', 'usage_summary']]);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/inference/model-cards/'.$cardId, [
                'display_name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Updated');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/inference/model-cards/'.$cardId)
            ->assertOk();

        $this->assertDatabaseMissing('ai_model_cards', ['id' => $cardId]);
    }

    public function test_management_can_view_but_not_create(): void
    {
        $management = $this->userWithRole('Management');

        $this->actingAsJwt($management)
            ->getJson('/api/v1/inference/model-cards')
            ->assertOk();

        $this->actingAsJwt($management)
            ->postJson('/api/v1/inference/model-cards', [
                'slug' => 'blocked-model',
                'display_name' => 'Blocked',
            ])
            ->assertForbidden();
    }

    public function test_provider_model_rate_sync(): void
    {
        $admin = $this->userWithRole('Admin');
        $provider = InferenceProvider::query()->where('slug', 'openwebui-9router')->firstOrFail();
        $card = AiModelCard::query()->where('slug', 'gpt-5.6-luna')->firstOrFail();

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/inference/providers/'.$provider->id.'/rates', [
                'rates' => [
                    [
                        'model_card_id' => $card->id,
                        'input_per_million_usd' => 1.25,
                        'output_per_million_usd' => 6.5,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.model_rates.0.input_per_million_usd', 1.25);

        $this->assertDatabaseHas('ai_provider_model_rates', [
            'inference_provider_id' => $provider->id,
            'model_card_id' => $card->id,
            'input_per_million_usd' => '1.2500',
            'output_per_million_usd' => '6.5000',
        ]);
    }

    public function test_provider_crud(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/inference/providers', [
                'slug' => 'test-provider',
                'name' => 'Test Provider',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'test-provider');

        $providerId = InferenceProvider::query()->where('slug', 'test-provider')->value('id');

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/inference/providers/'.$providerId, [
                'name' => 'Updated Provider',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Provider');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/inference/providers/'.$providerId)
            ->assertOk();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
