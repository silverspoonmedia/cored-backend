<?php

namespace App\Domain\PaymentGateway\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'kind' => PaymentChannelKind::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(PaymentChannelRoute::class);
    }
}
