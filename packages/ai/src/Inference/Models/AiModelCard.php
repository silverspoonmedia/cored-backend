<?php

namespace App\Domain\Inference\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModelCard extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'display_name',
        'is_active',
        'needs_rate_classification',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'needs_rate_classification' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProviderModelRate, $this>
     */
    public function providerModelRates(): HasMany
    {
        return $this->hasMany(ProviderModelRate::class, 'model_card_id');
    }

    /**
     * @return HasMany<AiModelRateSnapshot, $this>
     */
    public function rateSnapshots(): HasMany
    {
        return $this->hasMany(AiModelRateSnapshot::class, 'model_card_id');
    }
}
