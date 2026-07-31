<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithWebAuth;
use Tests\TestCase;

class PaymentIncomeWebTest extends TestCase
{
    use InteractsWithWebAuth;
    use RefreshDatabase;

    public function test_income_page_loads_for_authenticated_user(): void
    {
        $this->withoutVite();

        Http::fake([
            '*/api/v1/payments/income*' => Http::response([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'invoice_count' => 0,
                        'service_revenue' => 0,
                        'service_revenue_formatted' => 'IDR 0.00',
                        'buyer_fees' => 0,
                        'buyer_fees_formatted' => 'IDR 0.00',
                        'cash_collected' => 0,
                        'cash_collected_formatted' => 'IDR 0.00',
                        'avg_order_value' => 0,
                        'avg_order_value_formatted' => 'IDR 0.00',
                    ],
                    'pipeline' => [],
                    'by_gateway' => [],
                    'by_channel' => [],
                    'by_invoiceable_type' => [],
                    'daily' => [],
                ],
            ], 200),
        ]);

        $user = $this->actingWithJwtCookie(User::factory()->create());

        $user->get(route('payments.income.index'))
            ->assertOk()
            ->assertSee(__('web/payments.income.title'), false);
    }
}
