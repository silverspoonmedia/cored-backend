<?php

namespace Tests\Domain\Inference\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InferenceUsageApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(InferenceCatalogSeeder::class);
    }

    public function test_usage_overview_and_events(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = User::factory()->create();
        $card = AiModelCard::query()->where('slug', 'gpt-5.6-luna')->firstOrFail();

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'billing_source' => 'prepaid',
            'stage' => 'vision',
            'alias_model' => 'gpt-5.6-luna',
            'resolved_model' => 'gpt-5.6-luna',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 2000,
            'created_at' => now(),
        ]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/inference/usage/overview?model_slug=gpt-5.6-luna')
            ->assertOk()
            ->assertJsonPath('data.totals.event_count', 1)
            ->assertJsonPath('data.totals.total_tokens', 150);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/inference/usage-events?model_slug=gpt-5.6-luna')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.resolved_model', 'gpt-5.6-luna');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/inference/model-cards/'.$card->id)
            ->assertOk()
            ->assertJsonPath('data.usage_summary.event_count', 1);
    }

    public function test_management_can_read_usage_but_user_cannot(): void
    {
        $management = $this->userWithRole('Management');
        $user = $this->userWithRole('User');

        $this->actingAsJwt($management)
            ->getJson('/api/v1/inference/usage/overview')
            ->assertOk();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/inference/usage/overview')
            ->assertForbidden();
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
