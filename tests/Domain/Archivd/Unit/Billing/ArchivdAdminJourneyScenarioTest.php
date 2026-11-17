<?php

namespace Tests\Domain\Archivd\Unit\Billing;

use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\MainPlanChangeCalculator;
use App\Domain\Service\Services\ServiceAddonRenewalService;
use App\Domain\Service\Services\ServiceCheckoutService;
use App\Domain\Service\Services\ServiceFulfillmentService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

/**
 * Multi-month narrative journey for admin@example.com.
 *
 * Chronology (2026, fixed 30-day billing periods):
 *  Jan 18  buy Standard 1 TB → ends Feb 17
 *  Jan 27  +100 GB ×2 → anniversary Feb 26
 *  Jan 30  +500 GB (inherits Feb 26)
 *  Feb  3  +500 GB (mid-cycle; ends_at frozen)
 *  Feb  4  +100 GB (mid-cycle)
 *  Feb 17  auto-renew main 1 TB → ends Mar 19
 *  Feb 26  addon stack renewal pay → ends Mar 28
 *  Mar 15  upgrade Standard 2 TB → ends Apr 14
 *  Mar 28  addon stack renewal pay → ends Apr 27
 *  Apr 14  downgrade Standard 1 TB (credit from list 2TB)
 */
class ArchivdAdminJourneyScenarioTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    private const PRICE_1TB = 24.95;

    private const PRICE_2TB = 54.95;

    private const PRICE_100 = 2.95;

    private const PRICE_500 = 14.50;

    private User $user;

    private Service $main1;

    private Service $main2;

    private Service $addon100;

    private Service $addon500;

    /** @var list<array<string, mixed>> */
    private array $journal = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
        config(['archivd.default_quota_bytes' => ArchivdCatalog::freeBytes()]);

        Http::fake([
            'api.frankfurter.app/*' => Http::response(['rates' => ['IDR' => 16000]], 200),
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Admin Journey',
        ]);

        $this->main1 = Service::factory()->archivdMain1Tb()->create();
        $this->main2 = Service::factory()->archivdMain2Tb()->create();
        $this->addon100 = Service::factory()->archivdAddon100Gb()->create();
        $this->addon500 = Service::factory()->archivdAddon500Gb()->create();

        ServicePrice::factory()->usd(self::PRICE_1TB)->create(['service_id' => $this->main1->id]);
        ServicePrice::factory()->usd(self::PRICE_2TB)->create(['service_id' => $this->main2->id]);
        ServicePrice::factory()->usd(self::PRICE_100)->create(['service_id' => $this->addon100->id]);
        ServicePrice::factory()->usd(self::PRICE_500)->create(['service_id' => $this->addon500->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_multi_month_main_and_addon_journey(): void
    {
        // ── January ──────────────────────────────────────────────
        $this->at('2026-01-18 12:00:00');
        $janMain = $this->checkout($this->main1);
        $this->assertSame(PurchaseChangeType::New->value, $janMain['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $janMain['quote']['charge_amount'], 0.01);
        $this->assertPaidMain($this->main1);
        $this->assertMainEndsAt('2026-02-17 12:00:00');
        $this->assertQuota(ArchivdCatalog::freeBytes() + ArchivdCatalog::main1TbBytes());
        $this->log('Jan18 buy 1TB', $janMain);

        $this->at('2026-01-27 12:00:00');
        $a1 = $this->checkout($this->addon100);
        $a2 = $this->checkout($this->addon100);
        $this->assertSame(PurchaseChangeType::AddonUnit->value, $a1['quote']['change_type']);
        $this->assertAddonQty($this->addon100, 2);
        $this->assertAddonEndsAt($this->addon100, '2026-02-26 12:00:00');
        $this->log('Jan27 +100 x2', ['qty100' => 2, 'ends' => '2026-02-26']);

        $this->at('2026-01-30 12:00:00');
        $this->checkout($this->addon500);
        $this->assertAddonQty($this->addon500, 1);
        $this->assertAddonEndsAt($this->addon500, '2026-02-26 12:00:00');
        $this->assertSharedAddonAnniversary('2026-02-26 12:00:00');
        $quotaJanEnd = ArchivdCatalog::freeBytes()
            + ArchivdCatalog::main1TbBytes()
            + (2 * ArchivdCatalog::addon100GbBytes())
            + ArchivdCatalog::addon500GbBytes();
        $this->assertQuota($quotaJanEnd);
        $this->log('Jan30 +500', ['qty500' => 1, 'quota' => $quotaJanEnd]);

        // ── February (mid-cycle units before main renew & addon bill) ──
        $this->at('2026-02-03 12:00:00');
        $this->checkout($this->addon500);
        $this->assertAddonQty($this->addon500, 2);
        $this->assertAddonEndsAt($this->addon500, '2026-02-26 12:00:00');
        $this->log('Feb3 +500 mid-cycle', ['qty500' => 2, 'ends_frozen' => true]);

        $this->at('2026-02-04 12:00:00');
        $this->checkout($this->addon100);
        $this->assertAddonQty($this->addon100, 3);
        $this->assertAddonEndsAt($this->addon100, '2026-02-26 12:00:00');
        $this->log('Feb4 +100 mid-cycle', ['qty100' => 3, 'ends_frozen' => true]);

        $this->at('2026-02-17 12:00:00');
        $febRenew = $this->checkout($this->main1);
        $this->assertSame(PurchaseChangeType::Renew->value, $febRenew['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $febRenew['quote']['charge_amount'], 0.01);
        $this->assertPaidMain($this->main1);
        // renew stacks from remaining end (equal-now → base now → Mar 19)
        $this->assertMainEndsAt('2026-03-19 12:00:00');
        $this->log('Feb17 main renew 1TB', $febRenew);

        $this->at('2026-02-26 12:00:00');
        $expectedStack = (3 * self::PRICE_100) + (2 * self::PRICE_500); // 8.85 + 29 = 37.85
        $renewalFeb = $this->runAddonAnniversary();
        $this->assertNotNull($renewalFeb);
        $this->assertEqualsWithDelta($expectedStack, (float) $renewalFeb->display_amount, 0.01);
        $this->assertCount(2, $renewalFeb->lines);
        $this->assertSame(PurchaseStatus::Paid, $renewalFeb->fresh()->status);
        $this->assertAddonQty($this->addon100, 3);
        $this->assertAddonQty($this->addon500, 2);
        $this->assertSharedAddonAnniversary('2026-03-28 12:00:00');
        $quotaAfterFeb = ArchivdCatalog::freeBytes()
            + ArchivdCatalog::main1TbBytes()
            + (3 * ArchivdCatalog::addon100GbBytes())
            + (2 * ArchivdCatalog::addon500GbBytes());
        $this->assertQuota($quotaAfterFeb);
        $this->log('Feb26 addon stack renew', [
            'amount' => $expectedStack,
            'ends' => '2026-03-28',
            'note' => 'mid-cycle Feb units included full in stack (no proration)',
        ]);

        // ── March ────────────────────────────────────────────────
        $this->at('2026-03-15 12:00:00');
        $upgrade = $this->checkout($this->main2);
        $this->assertSame(PurchaseChangeType::Upgrade->value, $upgrade['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_2TB, $upgrade['quote']['list_amount'], 0.01);
        // ~4 days left of 1TB at list 24.95 → credit ~3.327
        $this->assertGreaterThan(0.0, $upgrade['quote']['credit_amount']);
        $this->assertLessThan(self::PRICE_2TB, $upgrade['quote']['charge_amount']);
        $this->assertEqualsWithDelta(
            self::PRICE_2TB - $upgrade['quote']['credit_amount'],
            $upgrade['quote']['charge_amount'],
            0.02,
        );
        $this->assertPaidMain($this->main2);
        $this->assertMainExpired($this->main1);
        // switch: now + 30 days + surplus (0 expected when credit < list)
        $this->assertSame(0, $upgrade['quote']['surplus_days']);
        $this->assertMainEndsAt('2026-04-14 12:00:00');
        $quotaMar = ArchivdCatalog::freeBytes()
            + ArchivdCatalog::main2TbBytes()
            + (3 * ArchivdCatalog::addon100GbBytes())
            + (2 * ArchivdCatalog::addon500GbBytes());
        $this->assertQuota($quotaMar);
        $this->log('Mar15 upgrade 2TB', $upgrade);

        $this->at('2026-03-28 12:00:00');
        $renewalMar = $this->runAddonAnniversary();
        $this->assertNotNull($renewalMar);
        $this->assertEqualsWithDelta($expectedStack, (float) $renewalMar->display_amount, 0.01);
        $this->assertSharedAddonAnniversary('2026-04-27 12:00:00');
        $this->assertAddonQty($this->addon100, 3);
        $this->assertAddonQty($this->addon500, 2);
        $this->log('Mar28 addon stack renew', ['amount' => $expectedStack, 'ends' => '2026-04-27']);

        // ── April ────────────────────────────────────────────────
        $this->at('2026-04-14 12:00:00');
        $downgrade = $this->checkout($this->main1);
        $this->assertSame(PurchaseChangeType::Downgrade->value, $downgrade['quote']['change_type']);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $downgrade['quote']['list_amount'], 0.01);
        // Credit base = list of 2TB (54.95), not net upgrade charge
        // At exact ends_at of 2TB period, remaining credit ≈ 0
        $this->assertEqualsWithDelta(0.0, $downgrade['quote']['credit_amount'], 0.05);
        $this->assertEqualsWithDelta(self::PRICE_1TB, $downgrade['quote']['charge_amount'], 0.05);
        $this->assertPaidMain($this->main1);
        $this->assertMainExpired($this->main2);
        $quotaApr = ArchivdCatalog::freeBytes()
            + ArchivdCatalog::main1TbBytes()
            + (3 * ArchivdCatalog::addon100GbBytes())
            + (2 * ArchivdCatalog::addon500GbBytes());
        $this->assertQuota($quotaApr);
        $this->log('Apr14 downgrade 1TB', [
            'quote' => $downgrade['quote'],
            'credit_base_is_list' => true,
        ]);

        // Final structural invariants
        $this->assertSame(
            1,
            ServiceEntitlement::query()
                ->where('user_id', $this->user->id)
                ->where('status', EntitlementStatus::Active)
                ->whereHas('service', fn ($q) => $q->where('plan_kind', PlanKind::Main))
                ->count(),
            'exactly one active main',
        );
        $this->assertSharedAddonAnniversary('2026-04-27 12:00:00');

        // Keep journal available if assertion fails later; dump on success for review
        $this->assertNotEmpty($this->journal);
        // phpunit -v will not print this; intentional structured trail for post-run review
        fwrite(STDERR, "\n=== JOURNEY JOURNAL ===\n".json_encode($this->journal, JSON_PRETTY_PRINT)."\n");
    }

    /**
     * @return array{purchase: ServicePurchase, invoice: mixed, quote: array<string, mixed>}
     */
    private function checkout(Service $service): array
    {
        $service->load('prices');
        $result = app(ServiceCheckoutService::class)->checkout($service, $this->user, 'USD');

        // Positive-charge purchases stay pending with invoice — simulate PG paid.
        if ($result['purchase']->status === PurchaseStatus::Pending) {
            app(ServiceFulfillmentService::class)->fulfill($result['purchase']);
            $result['purchase']->refresh();
        }

        return $result;
    }

    private function runAddonAnniversary(): ?ServiceAddonRenewal
    {
        $renewal = app(ServiceAddonRenewalService::class)->createForUser($this->user->id, 'USD');
        if ($renewal === null) {
            return null;
        }

        app(ServiceFulfillmentService::class)->fulfillAddonRenewal($renewal);

        return $renewal->fresh(['lines', 'invoice']);
    }

    private function at(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime));
    }

    private function assertPaidMain(Service $main): void
    {
        $row = ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('service_id', $main->id)
            ->first();

        $this->assertNotNull($row, "main entitlement missing for {$main->code}");
        $this->assertSame(EntitlementStatus::Active, $row->status);
        $this->assertSame(1, (int) $row->quantity);
    }

    private function assertMainExpired(Service $main): void
    {
        $row = ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('service_id', $main->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(EntitlementStatus::Expired, $row->status);
    }

    private function assertMainEndsAt(string $expected): void
    {
        $row = ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', fn ($q) => $q->where('plan_kind', PlanKind::Main))
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue(
            $row->ends_at->equalTo(Carbon::parse($expected)),
            "main ends_at expected {$expected}, got {$row->ends_at}",
        );
    }

    private function assertAddonQty(Service $addon, int $qty): void
    {
        $row = ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('service_id', $addon->id)
            ->first();

        $this->assertNotNull($row, "addon missing {$addon->code}");
        $this->assertSame($qty, (int) $row->quantity, "qty for {$addon->code}");
        $this->assertSame(EntitlementStatus::Active, $row->status);
    }

    private function assertAddonEndsAt(Service $addon, string $expected): void
    {
        $row = ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('service_id', $addon->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue(
            $row->ends_at->equalTo(Carbon::parse($expected)),
            "addon {$addon->code} ends_at expected {$expected}, got {$row->ends_at}",
        );
    }

    private function assertSharedAddonAnniversary(string $expected): void
    {
        $ends = app(MainPlanChangeCalculator::class)->sharedAddonEndsAt($this->user->id);
        $this->assertNotNull($ends);
        $this->assertTrue(
            $ends->equalTo(Carbon::parse($expected)),
            "shared addon ends expected {$expected}, got {$ends}",
        );
    }

    private function assertQuota(int $expected): void
    {
        $actual = app(QuotaService::class)->quotaBytesFor($this->user->id);
        $this->assertSame($expected, $actual, "quota bytes expected {$expected}, got {$actual}");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function log(string $step, array $data): void
    {
        $this->journal[] = [
            'at' => now()->toDateTimeString(),
            'step' => $step,
            'data' => $data,
        ];
    }
}
