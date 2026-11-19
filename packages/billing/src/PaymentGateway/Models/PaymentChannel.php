<?php

namespace App\Domain\PaymentGateway\Models;

use App\Domain\PaymentGateway\Enums\ChannelRoutingType;
use App\Domain\PaymentGateway\Enums\PaymentChannelKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentChannel extends Model
{
    use HasFactory, HasUuids;

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = [
        'code',
        'display_name',
        'kind',
        'is_active',
        'sort_order',
        'provider_bank_code',
        'cheap_alt_code',
        'routing_type',
        'policy_key',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PaymentChannelKind::class,
            'routing_type' => ChannelRoutingType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<PaymentChannelRoute, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(PaymentChannelRoute::class);
    }

    public function isQrisProxy(): bool
    {
        return $this->routing_type === ChannelRoutingType::QrisProxy;
    }
}
