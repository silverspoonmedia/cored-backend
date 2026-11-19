<?php

namespace App\Domain\Service\Tests\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Tests\Support\TestPlans;
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
                'code' => 'billing.plan.1tb.monthly',
                'name' => 'Standard 1 TB',
                'description' => 'Main plan 1 TB',
                'billing_interval' => 'monthly',
                'fulfillment_target' => 'test_target',
                'entitlement' => 'storage_quota',
                'plan_kind' => 'main',
                'storage_bytes' => 1099511627776,
                'status' => 'active',
                'prices' => [
                    ['currency' => 'USD', 'amount' => 24.95],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'billing.plan.1tb.monthly')
            ->assertJsonPath('data.plan_kind', 'main')
            ->assertJsonPath('data.storage_bytes', 1099511627776)
            ->assertJsonPath('data.prices.0.currency', 'USD')
            ->assertJsonPath('data.prices.0.amount', 24.95);

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
        Service::factory()->plan(TestPlans::main1Tb())->create();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_options_returns_enum_values(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/services/options')
            ->assertOk()
            ->assertJsonPath('data.billing_intervals', ['monthly', 'quarterly', 'yearly', 'once'])
            ->assertJsonPath('data.plan_kinds', ['main', 'addon'])
            ->assertJsonPath('data.statuses', ['draft', 'active', 'archived'])
            ->assertJsonPath('data.purchase_max_quantity', max(1, (int) setting_config('service.purchase.max_quantity', 99)));
    }

    public function test_guest_cannot_access_options(): void
    {
        $this->getJson('/api/v1/services/options')
            ->assertUnauthorized();
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

    public function test_query_paging_returns_items_envelope(): void
    {
        $admin = $this->userWithRole('Admin');
        Service::factory()->count(12)->create();

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/services?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.current_page', 1);

        $this->assertCount(10, $response->json('data.items'));
        $this->assertSame(12, $response->json('data.pagination.total'));
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
