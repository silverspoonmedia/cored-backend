<?php

namespace App\Domain\Service\Models;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use Database\Factories\ServicePurchaseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ServicePurchase extends Model
{
    /** @use HasFactory<ServicePurchaseFactory> */
    use HasFactory, HasUuids;

    protected $table = 'service_purchases';

    protected $fillable = [
        'user_id',
        'service_id',
        'display_currency',
        'display_amount',
        'list_amount',
        'credit_amount',
        'charge_amount',
        'surplus_days',
        'previous_entitlement_id',
        'change_type',
        'settlement_currency',
        'settlement_amount',
        'fx_rate',
        'fx_provider',
        'fx_fetched_at',
        'fx_rate_snapshot_id',
        'status',
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
            'list_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'charge_amount' => 'decimal:2',
            'surplus_days' => 'integer',
            'settlement_amount' => 'decimal:2',
            'fx_rate' => 'decimal:8',
            'fx_fetched_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'status' => PurchaseStatus::class,
            'change_type' => PurchaseChangeType::class,
        ];
    }

    protected static function newFactory(): ServicePurchaseFactory
    {
        return ServicePurchaseFactory::new();
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
     * @return BelongsTo<ExchangeRateSnapshot, $this>
     */
    public function fxRateSnapshot(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateSnapshot::class, 'fx_rate_snapshot_id');
    }

    /**
     * @return BelongsTo<ServiceEntitlement, $this>
     */
    public function previousEntitlement(): BelongsTo
    {
        return $this->belongsTo(ServiceEntitlement::class, 'previous_entitlement_id');
    }

    /**
     * @return MorphOne<Invoice, $this>
     */
    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }
}
