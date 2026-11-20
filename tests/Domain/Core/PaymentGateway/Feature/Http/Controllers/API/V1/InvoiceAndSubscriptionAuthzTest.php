<?php

namespace Tests\Domain\Core\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Enums\BillingCycle;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsPayloadHygiene;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InvoiceAndSubscriptionAuthzTest extends TestCase
{
    use AssertsPayloadHygiene;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_plain_user_cannot_list_or_show_invoices(): void
    {
        $invoice = $this->createInvoice();
        $user = $this->userWithRole('User');

        $this->assertForbiddenFor($user, 'GET', '/api/v1/invoices');
        $this->assertForbiddenFor($user, 'GET', '/api/v1/invoices/'.$invoice->id);
    }

    public function test_admin_can_list_and_show_invoices(): void
    {
        $invoice = $this->createInvoice();
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    }

    public function test_plain_user_cannot_list_show_or_mutate_subscriptions(): void
    {
        $subscription = $this->createSubscription();
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/subscriptions')
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->postJson('/api/v1/subscriptions', $this->subscriptionPayload())
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->putJson('/api/v1/subscriptions/'.$subscription->id, ['plan_name' => 'Hacked'])
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertForbidden();
    }

    public function test_management_can_read_but_not_mutate_subscriptions(): void
    {
        $subscription = $this->createSubscription();
        $management = $this->userWithRole('Management');

        $this->actingAsJwt($management)
            ->getJson('/api/v1/subscriptions')
            ->assertOk();

        $this->actingAsJwt($management)
            ->getJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);

        $this->actingAsJwt($management)
            ->postJson('/api/v1/subscriptions', $this->subscriptionPayload())
            ->assertForbidden();

        $this->actingAsJwt($management)
            ->putJson('/api/v1/subscriptions/'.$subscription->id, ['plan_name' => 'Hacked'])
            ->assertForbidden();

        $this->actingAsJwt($management)
            ->deleteJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertForbidden();
    }

    public function test_admin_can_create_and_delete_subscriptions(): void
    {
        $admin = $this->userWithRole('Admin');

        $created = $this->actingAsJwt($admin)
            ->postJson('/api/v1/subscriptions', $this->subscriptionPayload())
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/subscriptions/'.$created['id'])
            ->assertOk();
    }

    private function createInvoice(): Invoice
    {
        return Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 10_000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 10_000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
    }

    private function createSubscription(): Subscription
    {
        return Subscription::query()->create([
            'subscription_number' => Subscription::generateSubscriptionNumber(),
            'subscriber_name' => 'Jane Doe',
            'subscriber_email' => 'jane@example.com',
            'plan_name' => 'Pro Plan',
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 99_000,
            'currency' => 'IDR',
            'status' => SubscriptionStatus::Trial,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionPayload(): array
    {
        return [
            'subscriber_name' => 'New Subscriber',
            'subscriber_email' => 'new@example.com',
            'plan_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'amount' => 49000,
            'currency' => 'IDR',
        ];
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
