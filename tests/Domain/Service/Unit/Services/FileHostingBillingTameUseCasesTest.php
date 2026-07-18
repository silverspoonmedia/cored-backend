<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\Domain\Service\Support\FileHostingBillingScenarioHarness;
use Tests\TestCase;

/**
 * Tame use cases T01–T15: single-intent happy paths.
 */
class FileHostingBillingTameUseCasesTest extends TestCase
{
    use FileHostingBillingScenarioHarness;
    use RefreshDatabase;
    use SeedsCurrencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
        Http::fake(['api.frankfurter.app/*' => Http::response(['rates' => ['IDR' => 16000]], 200)]);
        $this->bootBillingHarness();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_T01_free_user_no_purchase(): void
    {
        $this->assertSame($this->free(), $this->quotaBytes());
        $this->assertNull($this->activeMain());
        $this->assertSame(0, ServiceEntitlement::query()->where('user_id', $this->user->id)->count());
    }

    public function test_T02_new_buy_1tb(): void
    {
        $this->at('2026-01-18 12:00:00');
        $r = $this->checkout($this->main1);

        $this->assertSame(PurchaseChangeType::New->value, $r['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $r['quote']['charge_amount'], 0.01);
        $this->assertSame(PurchaseStatus::Paid, $r['purchase']->status);
        $main = $this->activeMain();
        $this->assertNotNull($main);
        $this->assertTrue($main->ends_at->equalTo(Carbon::parse('2026-02-17 12:00:00')));
        $this->assertSame($this->expectedQuota($this->main1), $this->quotaBytes());
    }

    public function test_T03_new_buy_2tb(): void
    {
        $this->at('2026-01-18 12:00:00');
        $r = $this->checkout($this->main2);

        $this->assertSame(PurchaseChangeType::New->value, $r['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_2TB, $r['quote']['charge_amount'], 0.01);
        $this->assertSame($this->expectedQuota($this->main2), $this->quotaBytes());
    }

    public function test_T04_renew_same_1tb_mid_period(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);

        $this->at('2026-02-01 12:00:00');
        $r = $this->checkout($this->main1);

        $this->assertSame(PurchaseChangeType::Renew->value, $r['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $r['quote']['charge_amount'], 0.01);
        // stacks from remaining end 17 Feb → 19 Mar (+30d)
        $this->assertTrue($this->activeMain()->ends_at->equalTo(Carbon::parse('2026-03-19 12:00:00')));
    }

    public function test_T05_renew_on_exact_ends_at(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);

        $this->at('2026-02-17 12:00:00');
        $r = $this->checkout($this->main1);

        $this->assertSame(PurchaseChangeType::Renew->value, $r['quote']['change_type']);
        $this->assertTrue($this->activeMain()->ends_at->equalTo(Carbon::parse('2026-03-19 12:00:00')));
    }

    public function test_T06_first_addon_100_only_no_main(): void
    {
        $this->at('2026-01-27 12:00:00');
        $r = $this->checkout($this->addon100);

        $this->assertSame(PurchaseChangeType::AddonUnit->value, $r['quote']['change_type']);
        $ent = $this->entitlementFor($this->addon100);
        $this->assertSame(1, (int) $ent->quantity);
        $this->assertTrue($ent->ends_at->equalTo(Carbon::parse('2026-02-26 12:00:00')));
        $this->assertSame($this->expectedQuota(null, 1, 0), $this->quotaBytes());
    }

    public function test_T07_first_addon_500_only(): void
    {
        $this->at('2026-01-30 12:00:00');
        $this->checkout($this->addon500);

        $this->assertSame(1, (int) $this->entitlementFor($this->addon500)->quantity);
        $this->assertSame($this->expectedQuota(null, 0, 1), $this->quotaBytes());
    }

    public function test_T08_same_day_second_100_freezes_ends(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $ends = $this->entitlementFor($this->addon100)->ends_at->copy();

        $this->checkout($this->addon100);
        $ent = $this->entitlementFor($this->addon100);
        $this->assertSame(2, (int) $ent->quantity);
        $this->assertTrue($ent->ends_at->equalTo($ends));
    }

    public function test_T09_100_then_500_share_ends(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->at('2026-01-30 12:00:00');
        $this->checkout($this->addon500);

        $e100 = $this->entitlementFor($this->addon100);
        $e500 = $this->entitlementFor($this->addon500);
        $this->assertTrue($e100->ends_at->equalTo($e500->ends_at));
        $this->assertTrue($e100->ends_at->equalTo(Carbon::parse('2026-02-26 12:00:00')));
    }

    public function test_T10_addon_anniversary_pay_full_stack(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon100);
        $this->checkout($this->addon500);

        $this->at('2026-02-26 12:00:00');
        $renewal = $this->payAddonRenewal();
        $this->assertNotNull($renewal);
        $expected = (2 * self::PRICE_100) + self::PRICE_500;
        $this->assertEqualsWithDelta($expected, (float) $renewal->display_amount, 0.01);
        $this->assertSame(2, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertSame(1, (int) $this->entitlementFor($this->addon500)->quantity);
        $this->assertTrue($this->sharedAddonEndsAt()->equalTo(Carbon::parse('2026-03-28 12:00:00')));
    }

    public function test_T11_upgrade_1tb_to_2tb_mid_cycle(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);

        $this->at('2026-02-01 12:00:00');
        $r = $this->checkout($this->main2);

        $this->assertSame(PurchaseChangeType::Upgrade->value, $r['quote']['change_type']);
        $this->assertGreaterThan(0.0, $r['quote']['credit_amount']);
        $this->assertEqualsWithDelta(
            self::PRICE_2TB - $r['quote']['credit_amount'],
            $r['quote']['charge_amount'],
            0.02,
        );
        $this->assertSame(1, $this->activeMainCount());
        $this->assertSame($this->main2->id, $this->activeMain()->service_id);
        $this->assertSame(EntitlementStatus::Expired, $this->entitlementFor($this->main1)->status);
    }

    public function test_T12_downgrade_2tb_to_1tb_mid_cycle(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main2);

        $this->at('2026-02-01 12:00:00');
        $r = $this->checkout($this->main1);

        $this->assertSame(PurchaseChangeType::Downgrade->value, $r['quote']['change_type']);
        $this->assertGreaterThan(0.0, $r['quote']['credit_amount']);
        $this->assertSame($this->main1->id, $this->activeMain()->service_id);
    }

    public function test_T13_zero_charge_downgrade_immediate_fulfill(): void
    {
        // Day-1 of $54.95 period: huge credit vs $24.95 list → charge 0 + surplus
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main2);

        $this->at('2026-01-19 12:00:00');
        $r = $this->checkout($this->main1);

        $this->assertSame(PurchaseChangeType::Downgrade->value, $r['quote']['change_type']);
        $this->assertSame(0.0, $r['quote']['charge_amount']);
        $this->assertGreaterThan(0, $r['quote']['surplus_days']);
        $this->assertSame(PurchaseStatus::Paid, $r['purchase']->status);
        $this->assertNull($r['purchase']->invoice);
    }

    public function test_T14_main_exclusive_after_switch(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-02-01 12:00:00');
        $this->checkout($this->main2);

        $this->assertSame(1, $this->activeMainCount());
        $this->assertSame(EntitlementStatus::Expired, $this->entitlementFor($this->main1)->status);
        $this->assertSame(EntitlementStatus::Active, $this->entitlementFor($this->main2)->status);
    }

    public function test_T15_free_always_stacks_with_paid(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->checkout($this->addon100);

        $quota = $this->quotaBytes();
        $this->assertGreaterThanOrEqual($this->free(), $quota);
        $this->assertSame(
            $this->free() + FileHostingCatalog::main1TbBytes() + FileHostingCatalog::addon100GbBytes(),
            $quota,
        );
    }
}
