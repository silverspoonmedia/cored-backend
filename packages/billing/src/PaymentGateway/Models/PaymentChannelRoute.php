<?php

namespace App\Domain\PaymentGateway\Models;

use App\Domain\PaymentGateway\Enums\PaymentRouteRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentChannelRoute extends Model
{
    use HasFactory, HasUuids;

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = [
        'payment_channel_id',
        'payment_gateway_id',
        'provider_payment_type',
        'provider_payment_channel',
        'priority',
        'route_role',
        'settlement_days',
        'fee_flat',
        'fee_percent',
        'fee_vat_inclusive',
        'policy_tag',
        'is_policy_managed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'route_role' => PaymentRouteRole::class,
            'settlement_days' => 'integer',
            'fee_flat' => 'integer',
            'fee_percent' => 'decimal:3',
            'fee_vat_inclusive' => 'boolean',
            'is_policy_managed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PaymentChannel, $this>
     */
    public function paymentChannel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class);
    }

    /**
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }
}
