<?php

namespace Tests\Domain\Archivd\Unit\Billing;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceAddonRenewalService;
use App\Domain\Service\Services\ServiceFulfillmentService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

class AddonAnniversaryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
        config(['archivd.default_quota_bytes' => ArchivdCatalog::freeBytes()]);
    }

    public function test_three_addon_buys_same_day_keep_fixed_ends_at(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');

        $user = User::factory()->create();
        $addon = Service::factory()->archivdAddon100Gb()->create();

        $ends = null;
        for ($i = 0; $i < 3; $i++) {
            $purchase = ServicePurchase::factory()->pending()->create([
                'user_id' => $user->id,
                'service_id' => $addon->id,
                'change_type' => PurchaseChangeType::AddonUnit,
                'list_amount' => 2.95,
                'charge_amount' => 2.95,
                'display_amount' => 2.95,
            ]);
            app(ServiceFulfillmentService::class)->fulfill($purchase);

            $ent = ServiceEntitlement::query()
                ->where('user_id', $user->id)
                ->where('service_id', $addon->id)
                ->first();

            if ($ends === null) {
                $ends = $ent->ends_at->copy();
            } else {
                $this->assertTrue($ent->ends_at->equalTo($ends), 'ends_at must not move on unit buy');
            }
        }

        $ent = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $addon->id)
            ->first();

        $this->assertSame(3, $ent->quantity);
        // fixed 30-day period: Jan 1 → Jan 31
        $this->assertTrue($ends->equalTo(Carbon::parse('2026-01-31 10:00:00')));

        Carbon::setTestNow();
    }

    public function test_second_addon_sku_shares_anniversary(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');

        $user = User::factory()->create();
        $addon100 = Service::factory()->archivdAddon100Gb()->create();
        $addon500 = Service::factory()->archivdAddon500Gb()->create();

        $this->fulfillAddon($user, $addon100);

        Carbon::setTestNow('2026-01-10 10:00:00');
        $this->fulfillAddon($user, $addon500);

        $e100 = ServiceEntitlement::query()->where('service_id', $addon100->id)->first();
        $e500 = ServiceEntitlement::query()->where('service_id', $addon500->id)->first();

        $this->assertTrue($e100->ends_at->equalTo($e500->ends_at));
        $this->assertTrue($e100->ends_at->equalTo(Carbon::parse('2026-01-31 10:00:00')));

        Carbon::setTestNow();
    }

    public function test_addon_renewal_invoice_and_extend_on_pay(): void
    {
        Carbon::setTestNow('2026-02-01 12:00:00');

        Http::fake([
            'api.frankfurter.app/*' => Http::response(['rates' => ['IDR' => 16000]], 200),
        ]);

        $user = User::factory()->create();
        $addon100 = Service::factory()->archivdAddon100Gb()->create();
        $addon500 = Service::factory()->archivdAddon500Gb()->create();
        ServicePrice::factory()->usd(2.95)->create(['service_id' => $addon100->id]);
        ServicePrice::factory()->usd(14.50)->create(['service_id' => $addon500->id]);

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $addon100->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 6,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subHour(),
        ]);
        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $addon500->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 2,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subHour(),
        ]);

        $renewal = app(ServiceAddonRenewalService::class)->createForUser($user->id, 'USD');

        $this->assertNotNull($renewal);
        $this->assertSame(PurchaseStatus::Pending, $renewal->status);
        // 6*2.95 + 2*14.50 = 17.7 + 29 = 46.7
        $this->assertEqualsWithDelta(46.7, (float) $renewal->display_amount, 0.01);
        $this->assertCount(2, $renewal->lines);
        $this->assertNotNull($renewal->invoice);
        $this->assertSame('pending', $renewal->invoice->status);

        app(ServiceFulfillmentService::class)->fulfillAddonRenewal($renewal);

        $renewal->refresh();
        $this->assertSame(PurchaseStatus::Paid, $renewal->status);

        $e100 = ServiceEntitlement::query()->where('service_id', $addon100->id)->first();
        $e500 = ServiceEntitlement::query()->where('service_id', $addon500->id)->first();

        $this->assertSame(6, $e100->quantity);
        $this->assertSame(2, $e500->quantity);
        $this->assertTrue($e100->ends_at->equalTo($e500->ends_at));
        $this->assertTrue($e100->ends_at->greaterThan(now()));

        // Second create while still not due → null; while pending would also skip but now paid
        Carbon::setTestNow('2026-02-01 13:00:00');
        $again = app(ServiceAddonRenewalService::class)->createForUser($user->id, 'USD');
        $this->assertNull($again);

        Carbon::setTestNow();
    }

    private function fulfillAddon(User $user, Service $addon): void
    {
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'change_type' => PurchaseChangeType::AddonUnit,
            'list_amount' => 2.95,
            'charge_amount' => 2.95,
            'display_amount' => 2.95,
        ]);
        app(ServiceFulfillmentService::class)->fulfill($purchase);
    }
}
