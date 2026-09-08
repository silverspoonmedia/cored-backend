<?php

namespace App\Domain\Service\Models;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Support\ArchivdCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'services';

    protected $fillable = [
        'code',
        'name',
        'description',
        'billing_interval',
        'fulfillment_target',
        'entitlement',
        'plan_kind',
        'storage_bytes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'plan_kind' => PlanKind::class,
            'storage_bytes' => 'integer',
            'status' => ServiceStatus::class,
        ];
    }

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    /**
     * @return HasMany<ServicePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /**
     * @return HasMany<ServicePurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(ServicePurchase::class);
    }

    /**
     * @return HasMany<ServiceEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(ServiceEntitlement::class);
    }

    public function isSubscription(): bool
    {
        $interval = $this->billing_interval;

        return $interval instanceof BillingInterval
            ? $interval->isSubscription()
            : $interval !== BillingInterval::Once->value;
    }

    public function isOneTime(): bool
    {
        return ! $this->isSubscription();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('service');
    }

    public function isActive(): bool
    {
        return $this->status === ServiceStatus::Active;
    }

    /**
     * Paid main/addon that contributes storage_bytes (excludes workspace-slot SKU).
     */
    public function isArchivdStoragePlan(): bool
    {
        if (! $this->isArchivdCatalogPlan()) {
            return false;
        }

        return (int) $this->storage_bytes > 0;
    }

    /**
     * Any Archivd catalog SKU (storage main/addon or non-storage slot addon).
     */
    public function isArchivdCatalogPlan(): bool
    {
        if ($this->fulfillment_target !== ArchivdCatalog::FULFILLMENT_TARGET) {
            return false;
        }

        if (ArchivdCatalog::isFreeCode($this->code)) {
            return false;
        }

        $kind = $this->plan_kind;

        return $kind === PlanKind::Main || $kind === PlanKind::Addon;
    }

    /**
     * Any Microstock catalog SKU (free registration main or workspace-slot addon).
     */
    public function isMicrostockCatalogPlan(): bool
    {
        if ($this->fulfillment_target !== MicrostockCatalog::FULFILLMENT_TARGET) {
            return false;
        }

        $kind = $this->plan_kind;

        return $kind === PlanKind::Main || $kind === PlanKind::Addon;
    }

    public function isPaidMainPlan(): bool
    {
        return $this->isArchivdStoragePlan() && $this->plan_kind === PlanKind::Main;
    }

    /**
     * Stackable addon (Archivd storage/workspace slots or Microstock workspace slots).
     * Not mains. Drives quantity compounding + shared addon anniversary.
     */
    public function isAddonPlan(): bool
    {
        return ($this->isArchivdCatalogPlan() || $this->isMicrostockCatalogPlan())
            && $this->plan_kind === PlanKind::Addon;
    }

    public function isWorkspaceSlotAddon(): bool
    {
        if (! $this->isAddonPlan()) {
            return false;
        }

        return ArchivdCatalog::isWorkspaceSlotCode($this->code)
            || $this->entitlement === ArchivdCatalog::ENTITLEMENT_WORKSPACE_SLOTS
            || MicrostockCatalog::isWorkspaceSlotCode($this->code)
            || $this->entitlement === MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS;
    }

    public function priceFor(string $currency): ?ServicePrice
    {
        $currency = strtoupper($currency);

        return $this->prices->first(
            fn (ServicePrice $price): bool => strtoupper($price->currency) === $currency
        ) ?? $this->prices()
            ->where('currency', $currency)
            ->first();
    }
}
