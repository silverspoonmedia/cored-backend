<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class InvoiceCheckoutChannelsApiTest extends TestCase
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

    public function test_returns_quoted_channels_for_pending_invoice(): void
    {
        $invoice = $this->createPayableInvoice();
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/invoices/'.$invoice->id.'/checkout-channels');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $vaBca = collect($response->json('data'))->firstWhere('code', 'va_bca');
        $this->assertNotNull($vaBca);
        $this->assertSame(3500.0, (float) $vaBca['estimated_channel_fee']);
    }

    public function test_excludes_disabled_channel_from_checkout_catalog(): void
    {
        PaymentChannel::query()->where('code', 'qris')->update(['is_active' => false]);

        $invoice = $this->createPayableInvoice();
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/invoices/'.$invoice->id.'/checkout-channels');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertFalse($codes->contains('qris'));
    }

    public function test_forbidden_for_user_without_invoice_access(): void
    {
        $invoice = $this->createPayableInvoice();
        $user = User::factory()->create();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/invoices/'.$invoice->id.'/checkout-channels')
            ->assertForbidden();
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
