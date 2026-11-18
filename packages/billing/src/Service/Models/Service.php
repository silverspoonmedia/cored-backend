<?php

namespace App\Domain\Service\Models;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Database\Factories\ServiceFactory;
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
     * A Main/Addon row someone can actually buy: a plan kind set and the
     * `draft`/`archived` statuses excluded by {@see isActive()}.
     */
    public function isCatalogPlan(): bool
    {
        $kind = $this->plan_kind;

        return $kind === PlanKind::Main || $kind === PlanKind::Addon;
    }

    /**
     * Main plan with storage — the only SKU allowed to hold a paid storage tier.
     */
    public function isPaidMainPlan(): bool
    {
        return $this->isCatalogPlan()
            && $this->plan_kind === PlanKind::Main
            && (int) $this->storage_bytes > 0;
    }

    /**
     * Stackable addon (storage or workspace slots). Not mains.
     * Drives quantity compounding + shared addon anniversary.
     */
    public function isAddonPlan(): bool
    {
        return $this->isCatalogPlan() && $this->plan_kind === PlanKind::Addon;
    }

    /**
     * Any fixed-day storage plan: a paid main/addon that contributes storage.
     *
     * Which plans bill a fixed period is a product decision (`fixedPeriodDays`
     * on the fulfillment handler), so this only answers "does it add storage".
     */
    public function isStoragePlan(): bool
    {
        return $this->isCatalogPlan() && (int) $this->storage_bytes > 0;
    }

    /**
     * Addon whose `entitlement` grants workspace slots rather than storage.
     */
    public function isWorkspaceSlotAddon(): bool
    {
        if (! $this->isAddonPlan() || ! is_string($this->entitlement)) {
            return false;
        }

        return str_ends_with($this->entitlement, 'workspace_slots');
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
