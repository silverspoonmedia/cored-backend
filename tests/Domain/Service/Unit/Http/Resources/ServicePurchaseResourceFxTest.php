<?php

namespace Tests\Domain\Service\Unit\Http\Resources;

use App\Domain\Service\Http\Resources\ServicePurchaseResource;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePurchaseResourceFxTest extends TestCase
{
    use RefreshDatabase;

    public function test_fx_rate_label_includes_currency_pair(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create([
            'display_currency' => 'USD',
            'settlement_currency' => 'IDR',
            'fx_rate' => 16234.56789000,
        ]);

        $payload = (new ServicePurchaseResource($purchase))->resolve();

        $this->assertSame(16234.56789, $payload['fx_rate']);
        $this->assertStringContainsString('USD', (string) $payload['fx_rate_label']);
        $this->assertStringContainsString('IDR', (string) $payload['fx_rate_label']);
        $this->assertNotNull($payload['fx_rate_formatted']);
    }
}
