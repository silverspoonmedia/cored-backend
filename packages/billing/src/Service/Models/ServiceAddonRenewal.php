<?php

namespace App\Domain\Service\Models;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ServiceAddonRenewal extends Model
{
    use HasUuids;

    protected $table = 'service_addon_renewals';

    protected $fillable = [
        'user_id',
        'status',
        'display_currency',
        'display_amount',
        'settlement_currency',
        'settlement_amount',
        'fx_rate',
        'fx_provider',
        'fx_fetched_at',
        'fx_rate_snapshot_id',
        'billing_ends_at_before',
        'period_start',
        'period_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_amount' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'fx_rate' => 'decimal:8',
            'fx_fetched_at' => 'datetime',
            'billing_ends_at_before' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'status' => PurchaseStatus::class,
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
     * @return HasMany<ServiceAddonRenewalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ServiceAddonRenewalLine::class, 'service_addon_renewal_id');
    }

    /**
     * @return MorphOne<Invoice, $this>
     */
    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }
}
