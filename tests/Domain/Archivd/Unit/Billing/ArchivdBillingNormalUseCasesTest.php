<?php

namespace Tests\Domain\Archivd\Unit\Billing;

use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Services\ServiceEntitlementLifecycleService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\Domain\Archivd\Unit\Billing\ArchivdBillingScenarioHarness;
use Tests\TestCase;

/**
 * Normal use cases N01–N15: multi-step realistic journeys.
 */
class ArchivdBillingNormalUseCasesTest extends TestCase
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

    public function test_N01_jan_journey_1tb_plus_addons(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon100);
        $this->at('2026-01-30 12:00:00');
        $this->checkout($this->addon500);

        $this->assertSame(2, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertSame(1, (int) $this->entitlementFor($this->addon500)->quantity);
        $this->assertTrue($this->sharedAddonEndsAt()->equalTo(Carbon::parse('2026-02-26 12:00:00')));
        $this->assertSame($this->expectedQuota($this->main1, 2, 1), $this->quotaBytes());
    }

    public function test_N02_mid_cycle_addon_units_freeze_anniversary(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $ends = $this->entitlementFor($this->addon100)->ends_at->copy();

        $this->at('2026-02-03 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon500);

        $this->assertTrue($this->entitlementFor($this->addon100)->ends_at->equalTo($ends));
        $this->assertTrue($this->entitlementFor($this->addon500)->ends_at->equalTo($ends));
        $this->assertSame(2, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertSame(1, (int) $this->entitlementFor($this->addon500)->quantity);
    }

    public function test_N03_main_renew_independent_of_addon_clock(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $addonEnds = $this->entitlementFor($this->addon100)->ends_at->copy();

        $this->at('2026-02-17 12:00:00');
        $this->checkout($this->main1);

        $this->assertTrue($this->activeMain()->ends_at->equalTo(Carbon::parse('2026-03-19 12:00:00')));
        $this->assertTrue($this->entitlementFor($this->addon100)->ends_at->equalTo($addonEnds));
    }

    public function test_N04_stack_renew_includes_mid_cycle_qty(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->at('2026-02-03 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon500);
        $this->checkout($this->addon500);

        $this->at('2026-02-26 12:00:00');
        $renewal = $this->payAddonRenewal();
        $expected = (2 * self::PRICE_100) + (2 * self::PRICE_500);
        $this->assertEqualsWithDelta($expected, (float) $renewal->display_amount, 0.01);
        $this->assertSame(2, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertSame(2, (int) $this->entitlementFor($this->addon500)->quantity);
    }

    public function test_N05_upgrade_does_not_alter_addon_stack(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon500);
        $addonEnds = $this->sharedAddonEndsAt()->copy();

        $this->at('2026-02-10 12:00:00');
        $this->checkout($this->main2);

        $this->assertSame(1, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertSame(1, (int) $this->entitlementFor($this->addon500)->quantity);
        $this->assertTrue($this->sharedAddonEndsAt()->equalTo($addonEnds));
    }

    public function test_N06_downgrade_after_discounted_upgrade_uses_list_base(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-02-15 12:00:00');
        $up = $this->checkout($this->main2);
        $upgradeCharge = $up['quote']['charge_amount'];
        $this->assertLessThan(self::PRICE_2TB, $upgradeCharge);

        $this->at('2026-03-01 12:00:00');
        $main = $this->activeMain();
        $remainingDays = now()->floatDiffInDays($main->ends_at);
        $creditFromList = ($remainingDays / 30.0) * self::PRICE_2TB;

        $down = $this->checkout($this->main1);

        $this->assertSame(PurchaseChangeType::Downgrade->value, $down['quote']['change_type']);
        $this->assertEqualsWithDelta($creditFromList, $down['quote']['credit_amount'], 0.15);
        // list base is higher than prior net charge slice for same remaining days
        $this->assertGreaterThan($upgradeCharge * ($remainingDays / 30.0) - 0.01, $down['quote']['credit_amount']);
    }

    public function test_N07_two_consecutive_main_renews(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-02-17 12:00:00');
        $r1 = $this->checkout($this->main1);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $r1['quote']['charge_amount'], 0.01);
        $this->at('2026-03-19 12:00:00');
        $r2 = $this->checkout($this->main1);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $r2['quote']['charge_amount'], 0.01);
        $this->assertTrue($this->activeMain()->ends_at->equalTo(Carbon::parse('2026-04-18 12:00:00')));
    }

    public function test_N08_addon_only_two_anniversaries(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon100);

        $this->at('2026-02-26 12:00:00');
        $r1 = $this->payAddonRenewal();
        $this->assertEqualsWithDelta(2 * self::PRICE_100, (float) $r1->display_amount, 0.01);

        $this->at('2026-03-28 12:00:00');
        $r2 = $this->payAddonRenewal();
        $this->assertEqualsWithDelta(2 * self::PRICE_100, (float) $r2->display_amount, 0.01);
        $this->assertSame(2, (int) $this->entitlementFor($this->addon100)->quantity);
        $this->assertTrue($this->sharedAddonEndsAt()->equalTo(Carbon::parse('2026-04-27 12:00:00')));
    }

    public function test_N09_main_renew_leaves_addon_ends_untouched(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon500);
        $addonEnds = $this->entitlementFor($this->addon500)->ends_at->copy();

        $this->at('2026-02-17 12:00:00');
        $this->checkout($this->main1);

        $this->assertTrue($this->entitlementFor($this->addon500)->ends_at->equalTo($addonEnds));
    }

    public function test_N10_grace_main_still_counts_for_upgrade_credit(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);

        // Force into grace with future grace_ends_at and remaining "value" via ends_at slightly past
        $this->forceEntitlementStatus(
            $this->main1,
            EntitlementStatus::Grace,
            endsAt: Carbon::parse('2026-02-20 12:00:00'),
            graceEndsAt: Carbon::parse('2026-02-25 12:00:00'),
        );

        $this->at('2026-02-19 12:00:00');
        // ends_at still in future relative to now? set ends still ahead for credit
        $this->forceEntitlementStatus(
            $this->main1,
            EntitlementStatus::Grace,
            endsAt: Carbon::parse('2026-03-01 12:00:00'),
            graceEndsAt: Carbon::parse('2026-03-08 12:00:00'),
        );

        $r = $this->checkout($this->main2);
        $this->assertSame(PurchaseChangeType::Upgrade->value, $r['quote']['change_type']);
        $this->assertGreaterThan(0.0, $r['quote']['credit_amount']);
    }

    public function test_N11_expire_grace_main_recompute_free_plus_addons(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->checkout($this->addon100);

        $this->forceEntitlementStatus(
            $this->main1,
            EntitlementStatus::Grace,
            endsAt: Carbon::parse('2026-01-10 12:00:00'),
            graceEndsAt: Carbon::parse('2026-01-17 12:00:00'),
        );

        $this->at('2026-01-20 12:00:00');
        app(ServiceEntitlementLifecycleService::class)->expireGraceDue();

        $this->assertSame(EntitlementStatus::Expired, $this->entitlementFor($this->main1)->status);
        $this->assertSame($this->expectedQuota(null, 1, 0), $this->quotaBytes());
    }

    public function test_N12_shared_anniversary_later_sku_inherits(): void
    {
        $this->at('2026-01-05 12:00:00');
        $this->checkout($this->addon100);
        $this->at('2026-01-20 12:00:00');
        $this->checkout($this->addon500);

        $this->assertTrue(
            $this->entitlementFor($this->addon500)->ends_at->equalTo(
                $this->entitlementFor($this->addon100)->ends_at,
            ),
        );
        $this->assertTrue($this->sharedAddonEndsAt()->equalTo(Carbon::parse('2026-02-04 12:00:00')));
    }

    public function test_N13_double_scheduler_same_day(): void
    {
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);

        $this->at('2026-02-26 12:00:00');
        $first = $this->createAddonRenewalOnly();
        $this->assertNotNull($first);
        $this->assertSame(PurchaseStatus::Pending, $first->status);

        $second = $this->createAddonRenewalOnly();
        $this->assertNull($second, 'pending renewal blocks second create');

        $this->payAddonRenewal($first);
        $third = $this->createAddonRenewalOnly();
        $this->assertNull($third, 'after pay ends_at moved; not due same day');
    }

    public function test_N14_full_stack_math_6x100_plus_2x500(): void
    {
        $this->at('2026-01-01 12:00:00');
        for ($i = 0; $i < 6; $i++) {
            $this->checkout($this->addon100);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->checkout($this->addon500);
        }

        $this->at('2026-02-01 12:00:00');
        $renewal = $this->payAddonRenewal();
        $this->assertEqualsWithDelta(46.70, (float) $renewal->display_amount, 0.01);
    }

    public function test_N15_quota_matrix_free_1tb_3x100_2x500(): void
    {
        $this->at('2026-01-18 12:00:00');
        $this->checkout($this->main1);
        $this->at('2026-01-27 12:00:00');
        $this->checkout($this->addon100);
        $this->checkout($this->addon100);
        $this->checkout($this->addon100);
        $this->checkout($this->addon500);
        $this->checkout($this->addon500);

        $expected = ArchivdCatalog::freeBytes()
            + ArchivdCatalog::main1TbBytes()
            + (3 * ArchivdCatalog::addon100GbBytes())
            + (2 * ArchivdCatalog::addon500GbBytes());

        $this->assertSame($expected, $this->quotaBytes());
    }
}
