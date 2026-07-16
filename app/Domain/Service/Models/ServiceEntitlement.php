<?php

namespace App\Domain\Service\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceEntitlement extends Model
{
    use HasUuids;

    protected $table = 'service_entitlements';

    protected $fillable = [
        'user_id',
        'service_id',
        'status',
        'starts_at',
        'ends_at',
        'grace_ends_at',
        'grace_notified_at',
        'source_purchase_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EntitlementStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'grace_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<ServicePurchase, $this>
     */
    public function sourcePurchase(): BelongsTo
    {
        return $this->belongsTo(ServicePurchase::class, 'source_purchase_id');
    }

    public function isActive(): bool
    {
        return $this->status === EntitlementStatus::Active;
    }

    public function isInGrace(): bool
    {
        return $this->status === EntitlementStatus::Grace;
    }

    public function isPermanent(): bool
    {
        return $this->ends_at === null && $this->isActive();
    }

    public function grantsUnlimitedFileHosting(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $this->loadMissing('service');

        return $this->service?->fulfillment_target === 'file_hosting'
            && $this->service?->entitlement === 'unlimited_quota';
    }
}
