<?php

namespace Tests\Domain\Service\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsCurrencies;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedCurrencies();
    }

    public function test_admin_can_list_and_create_service_with_prices(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/services', [
                'code' => 'file-hosting.subscription.monthly',
                'name' => 'File Hosting',
                'description' => 'Unlimited storage',
                'billing_interval' => 'monthly',
                'fulfillment_target' => 'file_hosting',
                'entitlement' => 'unlimited_quota',
                'status' => 'active',
                'prices' => [
                    ['currency' => 'USD', 'amount' => 10],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'file-hosting.subscription.monthly')
            ->assertJsonPath('data.prices.0.currency', 'USD')
            ->assertJsonPath('data.prices.0.amount', 10);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_without_manage_cannot_create(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/services', [
                'code' => 'x.y',
                'name' => 'X',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_user_can_view_catalog(): void
    {
        $user = $this->userWithRole('User');
        Service::factory()->fileHostingMonthly()->create();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_duplicate_code_returns_422(): void
    {
        $admin = $this->userWithRole('Admin');
        Service::factory()->create(['code' => 'dup.code']);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/services', [
                'code' => 'dup.code',
                'name' => 'Dup',
                'billing_interval' => 'monthly',
                'status' => 'draft',
            ])
            ->assertStatus(422);
    }

    public function test_update_and_delete(): void
    {
        $admin = $this->userWithRole('Admin');
        $service = Service::factory()->create(['code' => 'edit.me']);
        ServicePrice::factory()->usd(5)->create(['service_id' => $service->id]);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/services/'.$service->id, [
                'name' => 'Edited',
                'prices' => [
                    ['currency' => 'USD', 'amount' => 12],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Edited')
            ->assertJsonPath('data.prices.0.amount', 12);

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/services/'.$service->id)
            ->assertOk();

        $this->assertSoftDeleted('services', ['id' => $service->id]);
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
