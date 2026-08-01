<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class PaymentChannelApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;
    use SetsUpPaymentChannels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedPaymentGatewaysAndChannels();
    }

    public function test_lists_available_payment_channels(): void
    {
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)->getJson('/api/v1/payment-channels');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('qris'));
        $this->assertTrue($codes->contains('va_bca'));
        $this->assertTrue($codes->contains('gopay'));
    }

    public function test_admin_can_disable_payment_channel(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/payment-channels/qris', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $this->assertFalse($channel->is_active);
    }

    public function test_admin_scope_lists_disabled_channels(): void
    {
        PaymentChannel::query()->where('code', 'qris')->update(['is_active' => false]);

        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $response = $this->actingAsJwt($admin)->getJson('/api/v1/payment-channels?scope=admin');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('qris'));

        $qris = collect($response->json('data'))->firstWhere('code', 'qris');
        $this->assertFalse($qris['is_active']);
        $this->assertIsArray($qris['routes'] ?? null);
        $this->assertNotEmpty($qris['routes']);
    }

    public function test_lists_channel_fee_estimates_when_subtotal_provided(): void
    {
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/payment-channels?subtotal=250000');

        $response->assertOk();

        $vaBca = collect($response->json('data'))->firstWhere('code', 'va_bca');
        $this->assertNotNull($vaBca);
        $this->assertSame(3500.0, (float) $vaBca['estimated_channel_fee']);
        $this->assertSame(253500.0, (float) $vaBca['estimated_customer_total']);
        $this->assertSame('ipaymu', $vaBca['gateway_name']);
    }

    public function test_admin_can_update_payment_channel_route(): void
    {
        $admin = $this->userWithRole('Admin');
        $channel = PaymentChannel::query()->where('code', 'qris')->with('routes.paymentGateway')->firstOrFail();
        $ipaymuRoute = $channel->routes->first(fn ($route) => $route->paymentGateway?->name === 'ipaymu');
        $this->assertNotNull($ipaymuRoute);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/payment-channel-routes/'.$ipaymuRoute->id, ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/payment-channel-routes/'.$ipaymuRoute->id, ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/payment-channel-routes/'.$ipaymuRoute->id, ['priority' => 5])
            ->assertUnprocessable();
    }

    public function test_admin_scope_includes_route_fee_and_settlement_metadata(): void
    {
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)->getJson('/api/v1/payment-channels?scope=admin');
        $qris = collect($response->json('data'))->firstWhere('code', 'qris');

        $this->assertNotNull($qris);
        $primary = collect($qris['routes'])->firstWhere('route_role', 'primary');
        $this->assertNotNull($primary);
        $this->assertSame(2, $primary['settlement_days']);
        $this->assertNotNull($primary['fee_label']);
    }

    public function test_admin_scope_requires_manage_payment_gateway_permission(): void
    {
        $manager = $this->userWithRole('Management');

        $this->actingAsJwt($manager)
            ->getJson('/api/v1/payment-channels?scope=admin')
            ->assertForbidden();
    }

    public function test_show_returns_not_found_for_disabled_channel(): void
    {
        PaymentChannel::query()->where('code', 'qris')->update(['is_active' => false]);

        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/payment-channels/qris')
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
