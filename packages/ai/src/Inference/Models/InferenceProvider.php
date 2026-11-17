<?php

namespace App\Domain\Inference\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InferenceProvider extends Model
{
    use HasUuids;

    protected $table = 'ai_inference_providers';

    protected $fillable = [
        'slug',
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProviderModelRate, $this>
     */
    public function modelRates(): HasMany
    {
        return $this->hasMany(ProviderModelRate::class, 'inference_provider_id');
    }
}
