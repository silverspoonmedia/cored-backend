<?php

namespace Tests\Domain\Core\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PaymentIncomeApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_overview_requires_view_invoice_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/payments/income')
            ->assertForbidden();
    }

    public function test_overview_returns_summary_for_admin(): void
    {
        Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'qris',
            'payment_channel_code' => 'qris',
            'subtotal' => 120_000,
            'payment_channel_fee' => 840,
            'customer_gross_total' => 120_840,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/payments/income?basis=paid_at')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.summary.invoice_count', 1)
            ->assertJsonPath('data.summary.service_revenue', 120000)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'pipeline',
                    'by_gateway',
                    'by_channel',
                    'by_invoiceable_type',
                    'daily',
                ],
            ]);
    }

    public function test_invoices_endpoint_returns_paginated_rows(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/payments/income/invoices?per_page=5')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
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
