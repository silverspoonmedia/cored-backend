<?php

namespace Tests\Domain\Archivd\Unit\Billing;

use App\Domain\Archivd\Services\ArchivdQuotaRecomputeService;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
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
use Illuminate\Support\Carbon;

/**
 * Shared setup/helpers for Archivd billing use-case matrix tests.
 */
trait ArchivdBillingScenarioHarness
{
    public const PRICE_1TB = 24.95;

    public const PRICE_2TB = 54.95;

    public const PRICE_100 = 2.95;

    public const PRICE_500 = 14.50;

    protected User $user;

    protected Service $main1;

    protected Service $main2;

    protected Service $addon100;

    protected Service $addon500;

    protected function bootBillingHarness(): void
    {
        config(['archivd.default_quota_bytes' => ArchivdCatalog::freeBytes()]);

        $this->user = User::factory()->create([
            'email' => 'billing-matrix@example.com',
            'name' => 'Billing Matrix',
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

    protected function at(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime));
    }

    /**
     * @return array{purchase: ServicePurchase, invoice: mixed, quote: array<string, mixed>}
     */
    protected function checkout(Service $service): array
    {
        $service->load('prices');
        $result = app(ServiceCheckoutService::class)->checkout($service, $this->user, 'USD');

        if ($result['purchase']->status === PurchaseStatus::Pending) {
            app(ServiceFulfillmentService::class)->fulfill($result['purchase']);
            $result['purchase']->refresh();
        }

        return $result;
    }

    protected function createAddonRenewalOnly(): ?ServiceAddonRenewal
    {
        return app(ServiceAddonRenewalService::class)->createForUser($this->user->id, 'USD');
    }

    protected function payAddonRenewal(?ServiceAddonRenewal $renewal = null): ?ServiceAddonRenewal
    {
        $renewal ??= $this->createAddonRenewalOnly();
        if ($renewal === null) {
            return null;
        }

        app(ServiceFulfillmentService::class)->fulfillAddonRenewal($renewal);

        return $renewal->fresh(['lines', 'invoice']);
    }

    protected function activeMain(): ?ServiceEntitlement
    {
        return ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', fn ($q) => $q->where('plan_kind', PlanKind::Main))
            ->with('service')
            ->first();
    }

    protected function entitlementFor(Service $service): ?ServiceEntitlement
    {
        return ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('service_id', $service->id)
            ->first();
    }

    protected function forceEntitlementStatus(Service $service, EntitlementStatus $status, ?Carbon $endsAt = null, ?Carbon $graceEndsAt = null): ServiceEntitlement
    {
        $row = $this->entitlementFor($service);
        if ($row === null) {
            throw new \RuntimeException("No entitlement for {$service->code}");
        }

        $row->status = $status;
        if ($endsAt !== null) {
            $row->ends_at = $endsAt;
        }
        if ($graceEndsAt !== null) {
            $row->grace_ends_at = $graceEndsAt;
        }
        $row->save();

        return $row->refresh();
    }

    protected function recomputeQuota(): int
    {
        app(ArchivdQuotaRecomputeService::class)->recompute($this->user->id);

        return app(QuotaService::class)->quotaBytesFor($this->user->id);
    }

    protected function quotaBytes(): int
    {
        return app(QuotaService::class)->quotaBytesFor($this->user->id);
    }

    protected function sharedAddonEndsAt(): ?Carbon
    {
        return app(MainPlanChangeCalculator::class)->sharedAddonEndsAt($this->user->id);
    }

    protected function activeMainCount(): int
    {
        return ServiceEntitlement::query()
            ->where('user_id', $this->user->id)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', fn ($q) => $q->where('plan_kind', PlanKind::Main))
            ->count();
    }

    protected function free(): int
    {
        return ArchivdCatalog::freeBytes();
    }

    protected function expectedQuota(?Service $main = null, int $qty100 = 0, int $qty500 = 0): int
    {
        $bytes = $this->free();
        if ($main !== null) {
            $bytes += (int) $main->storage_bytes;
        }
        $bytes += $qty100 * ArchivdCatalog::addon100GbBytes();
        $bytes += $qty500 * ArchivdCatalog::addon500GbBytes();

        return $bytes;
    }
}
