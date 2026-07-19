<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\UserQuota;
use App\Domain\FileHosting\Services\QuotaService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceEntitlementLifecycleService;
use App\Domain\Service\Services\ServiceFulfillmentService;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceEntitlementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $free;

    private int $main1Tb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->free = FileHostingCatalog::freeBytes();
        $this->main1Tb = FileHostingCatalog::main1TbBytes();
        config([
            'file-hosting.default_quota_bytes' => $this->free,
            'service.grace_days' => 7,
        ]);
    }

    public function test_fulfill_grants_active_entitlement_and_paid_quota(): void
    {
        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMain1Tb()->create();
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
        ]);

        app(ServiceFulfillmentService::class)->fulfill($purchase);

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Paid, $purchase->status);
        $this->assertNotNull($purchase->period_end);

        $expected = $this->free + $this->main1Tb;
        $quota = app(QuotaService::class)->usage($user->id);
        $this->assertSame($expected, $quota['quota_bytes']);
        $this->assertSame('paid', $quota['state']);
        $this->assertSame($expected, $quota['remaining_bytes']);

        app(QuotaService::class)->assertCanAccept($user->id, $this->main1Tb);
    }

    public function test_grace_blocks_upload(): void
    {
        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
            'status' => EntitlementStatus::Grace,
            'quantity' => 1,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(6),
        ]);

        UserQuota::query()->create([
            'user_id' => $user->id,
            'quota_bytes' => $this->free + $this->main1Tb,
            'source' => 'service_recompute',
            'source_id' => null,
        ]);

        $this->expectException(FileHostingException::class);
        $this->expectExceptionMessage(__('api/file-hosting.quota_grace_blocked'));

        app(QuotaService::class)->assertCanAccept($user->id, 100);
    }

    public function test_enter_grace_command_path_sets_status(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 1,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subHour(),
        ]);

        $count = app(ServiceEntitlementLifecycleService::class)->enterGraceForDue();

        $this->assertSame(1, $count);
        $ent = ServiceEntitlement::query()->where('user_id', $user->id)->first();
        $this->assertSame(EntitlementStatus::Grace, $ent->status);
        $this->assertNotNull($ent->grace_ends_at);
        $this->assertNotNull($ent->grace_notified_at);

        Carbon::setTestNow();
    }

    public function test_expire_recomputes_to_free_quota(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
            'status' => EntitlementStatus::Grace,
            'quantity' => 1,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(8),
            'grace_ends_at' => now()->subHour(),
        ]);

        UserQuota::query()->create([
            'user_id' => $user->id,
            'quota_bytes' => $this->free + $this->main1Tb,
            'source' => 'service_recompute',
            'source_id' => null,
        ]);

        $count = app(ServiceEntitlementLifecycleService::class)->expireGraceDue();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('file_hosting_user_quotas', [
            'user_id' => $user->id,
            'quota_bytes' => $this->free,
            'source' => 'service_recompute',
        ]);

        $quota = app(QuotaService::class)->usage($user->id);
        $this->assertSame($this->free, $quota['quota_bytes']);
        $this->assertSame('free', $quota['state']);

        Carbon::setTestNow();
    }
}
