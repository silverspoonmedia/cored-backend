<?php

namespace App\Domain\PaymentGateway\Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Services\InvoiceCsvExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use App\Domain\PaymentGateway\Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class InvoiceCsvExportTest extends TestCase
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

    public function test_api_export_streams_csv_with_money_columns(): void
    {
        $invoice = $this->createPayableInvoice('midtrans', [
            'payment_channel_code' => 'qris',
            'payment_channel_fee' => 700,
            'customer_gross_total' => 100_700,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->get('/api/v1/invoices/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('invoice_number', $content);
        $this->assertStringContainsString('payment_channel_fee', $content);
        $this->assertStringContainsString($invoice->invoice_number, $content);
        $this->assertStringContainsString('100700', preg_replace('/[^\d]/', '', $content) ?: '');
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
