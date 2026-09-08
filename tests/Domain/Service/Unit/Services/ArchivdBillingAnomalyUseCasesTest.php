<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Services\MainPlanChangeCalculator;
use App\Domain\Service\Support\ArchivdCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\Domain\Service\Support\ArchivdBillingScenarioHarness;
use Tests\TestCase;

/**
 * Anomaly use cases A01–A15: boundaries, traps, failure modes.
 * Document actual behavior; do not "fix" product logic in this suite.
 */
class ArchivdBillingAnomalyUseCasesTest extends TestCase
{
    use ArchivdBillingScenarioHarness;
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

    public function test_A01_credit_base_after_net_upgrade_uses_list_not_charge(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-02-15 12:00:00');
        $up = $this->checkout($this->main2);
        $netCharge = $up['quote']['charge_amount'];
        $this->assertLessThan(self::PRICE_2TB, $netCharge);

        $this->at('2026-03-01 12:00:00');
        $main = $this->activeMain();
        $remainingDays = now()->floatDiffInDays($main->ends_at);
        $creditFromList = ($remainingDays / 30.0) * self::PRICE_2TB;

        $down = $this->checkout($this->main1);
        $this->assertEqualsWithDelta($creditFromList, $down['quote']['credit_amount'], 0.15);
    }

    public function test_A02_zero_charge_switch_preserves_list_credit_base(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main2);

        $this->at('2026-01-19 12:00:00');
        $zero = $this->checkout($this->main1);
        $this->assertSame(0.0, $zero['quote']['charge_amount']);
        $this->assertSame(0.0, (float) $zero['purchase']->display_amount);
        $this->assertEqualsWithDelta(self::PRICE_1TB, (float) $zero['purchase']->list_amount, 0.01);

        // Still early in surplus-extended period — upgrade back to 2TB
        $this->at('2026-01-25 12:00:00');
        $again = $this->checkout($this->main2);

        $this->assertGreaterThan(0.0, $again['quote']['credit_amount']);
        $this->assertLessThan(self::PRICE_2TB, $again['quote']['charge_amount']);
    }

    public function test_A03_upgrade_on_last_day_credit_near_zero(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);

        $this->at('2026-02-17 11:00:00'); // ~1h left of fixed 30-day period
        $r = $this->checkout($this->main2);

        $this->assertSame(PurchaseChangeType::Upgrade->value, $r['quote']['change_type']);
        $this->assertLessThan(1.0, $r['quote']['credit_amount']);
        $this->assertEqualsWithDelta(self::PRICE_2TB, $r['quote']['charge_amount'], 1.0);
    }

    public function test_A04_downgrade_day1_large_surplus_days(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main2);

        $this->at('2026-01-19 12:00:00');
        $r = $this->checkout($this->main1);

        $this->assertGreaterThan(20, $r['quote']['surplus_days']);
        $expectedEnd = Carbon::parse('2026-01-19 12:00:00')
            ->addDays(30)
            ->addDays($r['quote']['surplus_days']);
        $this->assertTrue($this->activeMain()->ends_at->equalTo($expectedEnd));
    }

    public function test_A05_period_fixed_30_days_aligned_with_credit_divisor(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $ends = $this->activeMain()->ends_at;
        $periodDays = Carbon::parse('2026-01-18 12:00:00')->floatDiffInDays($ends);
        $this->assertEqualsWithDelta(30.0, $periodDays, 0.001);

        // Midpoint: 15 days left of 30-day period
        $this->at('2026-02-02 12:00:00');
        $remaining = now()->floatDiffInDays($ends);
        $credit = app(MainPlanChangeCalculator::class)
            ->quote($this->main2, $this->user->id, self::PRICE_2TB)
            ->creditAmount;

        $expected = ($remaining / 30.0) * self::PRICE_1TB;
        $this->assertEqualsWithDelta($expected, $credit, 0.05);
    }

    public function test_A06_renew_when_already_expired_no_grace(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->forceEntitlementStatus(
            $this->main1,
            EntitlementStatus::Expired,
            endsAt: Carbon::parse('2026-01-10 12:00:00'),
        );

        $this->at('2026-02-01 12:00:00');
        $r = $this->checkout($this->main1);

        // No active/grace main → treated as new full price
        $this->assertSame(PurchaseChangeType::New->value, $r['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $r['quote']['charge_amount'], 0.01);
        $this->assertSame(EntitlementStatus::Active, $this->entitlementFor($this->main1)->status);
    }

    public function test_A07_addon_buy_while_ends_at_already_due(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->assertSame(1, (int) $this->entitlementFor($this->addon100)->quantity);

        $this->at('2026-02-28 12:00:00'); // past ends, status still Active (sticky)
        $this->expectException(ServiceException::class);
        $this->checkout($this->addon100);
    }

    public function test_A08_unpaid_renewal_blocks_unit_buy(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);

        $this->at('2026-02-26 12:00:00');
        $pending = $this->createAddonRenewalOnly();
        $this->assertNotNull($pending);
        $this->assertSame(PurchaseStatus::Pending, $pending->status);

        // sticky access: entitlement still active (no auto-expire this task)
        $this->assertSame(EntitlementStatus::Active, $this->entitlementFor($this->addon100)->status);

        try {
            $this->checkout($this->addon100);
            $this->fail('Expected ServiceException for pending stack renewal');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        $this->assertSame(1, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertNull($this->createAddonRenewalOnly(), 'still one pending');
    }

    public function test_A09_two_pending_renewal_prevention(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->at('2026-02-26 12:00:00');

        $a = $this->createAddonRenewalOnly();
        $b = $this->createAddonRenewalOnly();
        $this->assertNotNull($a);
        $this->assertNull($b);
    }

    public function test_A10_equal_storage_mains_classified_by_storage_only(): void
    {
        // Fake second main with same storage as 1TB
        $clone = Service::query()->create([
            'code' => 'archivd.plan.1tb.alt.monthly',
            'name' => 'Standard 1 TB Alt',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Main,
            'storage_bytes' => ArchivdCatalog::main1TbBytes(),
            'status' => ServiceStatus::Active,
        ]);
        ServicePrice::factory()->usd(self::PRICE_1TB)->create(['service_id' => $clone->id]);

        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-02-01 12:00:00');
        $r = $this->checkout($clone);

        // storage equal → not upgrade (>) so downgrade branch
        $this->assertSame(PurchaseChangeType::Downgrade->value, $r['quote']['change_type']);
    }

    public function test_A11_checkout_free_code_blocked(): void
    {
        $free = Service::query()->create([
            'code' => ArchivdCatalog::CODE_FREE,
            'name' => 'Gratis',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Main,
            'storage_bytes' => ArchivdCatalog::freeBytes(),
            'status' => ServiceStatus::Active,
        ]);
        ServicePrice::factory()->usd(0)->create(['service_id' => $free->id]);

        $this->expectException(ServiceException::class);
        $this->checkout($free);
    }

    public function test_A12_unit_on_due_day_blocked_renew_then_unit_works(): void
    {
        // Order A: unit buy on due day is blocked
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->at('2026-02-26 12:00:00');
        try {
            $this->checkout($this->addon100);
            $this->fail('Expected ServiceException for due addon anniversary');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->statusCode());
        }
        $this->assertSame(1, (int) $this->entitlementFor($this->addon100)->quantity);

        // Order B: renew first, then unit buy allowed
        $this->forceEntitlementStatus(
            $this->addon100,
            EntitlementStatus::Expired,
            endsAt: Carbon::parse('2026-01-01 12:00:00'),
        );

        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon500);
        $this->at('2026-02-26 12:00:00');
        $renewalB = $this->payAddonRenewal();
        $this->assertNotNull($renewalB);
        $endsAfterRenew = $this->entitlementFor($this->addon500)->ends_at->copy();
        $this->checkout($this->addon500);
        $this->assertSame(2, (int) $this->entitlementFor($this->addon500)->quantity);
        $this->assertTrue($this->entitlementFor($this->addon500)->ends_at->equalTo($endsAfterRenew));
    }

    public function test_A13_main_switch_resets_period_to_now_not_old_anniversary(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);

        $this->at('2026-02-10 12:00:00');
        $this->checkout($this->main2);

        // switch: now + 30 days (not remaining of prior anniversary + period)
        $this->assertTrue($this->activeMain()->ends_at->equalTo(Carbon::parse('2026-03-12 12:00:00')));
    }

    public function test_A14_expired_addon_rebuy_resets_qty_to_one(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon100);
        $this->assertSame(2, (int) $this->entitlementFor($this->addon100)->quantity);

        $this->forceEntitlementStatus(
            $this->addon100,
            EntitlementStatus::Expired,
            endsAt: Carbon::parse('2026-01-20 12:00:00'),
        );

        $this->at('2026-02-10 12:00:00');
        $this->checkout($this->addon100);
        $this->assertSame(1, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertSame(EntitlementStatus::Active, $this->entitlementFor($this->addon100)->status);
    }

    public function test_A15_missing_usd_price_on_renewal_soft_fails(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon500);

        // Strip price from one SKU
        ServicePrice::query()->where('service_id', $this->addon500->id)->delete();

        $this->at('2026-02-26 12:00:00');
        $renewal = $this->createAddonRenewalOnly();

        // Soft skip missing price: still creates with remaining lines only (100 only)
        // or null if all lines skipped — assert no crash
        if ($renewal !== null) {
            $this->assertEqualsWithDelta(self::PRICE_100, (float) $renewal->display_amount, 0.01);
            $this->assertCount(1, $renewal->lines);
        } else {
            $this->assertNull($renewal);
        }
    }
}
