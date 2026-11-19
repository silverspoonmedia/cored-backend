<?php

namespace App\Domain\PaymentGateway\Models;

use App\Domain\PaymentGateway\Enums\PaymentRouteRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentChannelRouteSnapshot extends Model
{
    use HasUuids;

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = [
        'payment_channel_code',
        'payment_gateway_id',
        'provider_payment_type',
        'provider_payment_channel',
        'route_role',
        'settlement_days',
        'fee_flat',
        'fee_percent',
        'fee_vat_inclusive',
        'policy_tag',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'route_role' => PaymentRouteRole::class,
            'settlement_days' => 'integer',
            'fee_flat' => 'integer',
            'fee_percent' => 'decimal:3',
            'fee_vat_inclusive' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }

    public static function fromRoute(PaymentChannelRoute $route, string $channelCode): self
    {
        /** @var self $snapshot */
        $snapshot = static::query()->create([
            'payment_channel_code' => $channelCode,
            'payment_gateway_id' => $route->payment_gateway_id,
            'provider_payment_type' => $route->provider_payment_type,
            'provider_payment_channel' => $route->provider_payment_channel,
            'route_role' => $route->route_role,
            'settlement_days' => $route->settlement_days,
            'fee_flat' => $route->fee_flat,
            'fee_percent' => $route->fee_percent,
            'fee_vat_inclusive' => $route->fee_vat_inclusive,
            'policy_tag' => $route->policy_tag,
            'captured_at' => now(),
        ]);

        return $snapshot;
    }

    /**
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }
}
