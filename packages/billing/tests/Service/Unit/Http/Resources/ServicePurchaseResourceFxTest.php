<?php

namespace App\Domain\Service\Tests\Unit\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Http\Resources\ServicePurchaseResource;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Tests\TestCase;
use App\Support\Testing\Concerns\SeedsRolesAndPermissions;
use Illuminate\Http\Request;

class ServicePurchaseResourceFxTest extends TestCase
{
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_fx_rate_label_includes_currency_pair_for_staff(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create([
            'display_currency' => 'USD',
            'settlement_currency' => 'IDR',
            'fx_rate' => 16234.56789000,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $request = Request::create('/');
        $request->setUserResolver(fn () => $admin);

        $payload = (new ServicePurchaseResource($purchase))->resolve($request);

        $this->assertSame(16234.56789, $payload['fx_rate']);
        $this->assertStringContainsString('USD', (string) $payload['fx_rate_label']);
        $this->assertStringContainsString('IDR', (string) $payload['fx_rate_label']);
        $this->assertNotNull($payload['fx_rate_formatted']);
    }

    public function test_buyer_payload_omits_settlement_and_fx(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole('User');
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $buyer->id,
            'display_currency' => 'USD',
            'settlement_currency' => 'IDR',
            'fx_rate' => 18000,
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $buyer);

        $payload = (new ServicePurchaseResource($purchase))->resolve($request);

        $this->assertArrayNotHasKey('settlement_amount', $payload);
        $this->assertArrayNotHasKey('settlement_currency', $payload);
        $this->assertArrayNotHasKey('fx_rate', $payload);
        $this->assertArrayNotHasKey('fx_provider', $payload);
        $this->assertArrayNotHasKey('fx_rate_snapshot_id', $payload);
    }
}
